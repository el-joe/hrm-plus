<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Actions\Attendance\ManageAttendanceDevice;
use App\Enums\DeviceConnectionMode;
use App\Enums\DeviceHealth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Attendance\DeviceRequest;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceEvent;
use App\Models\AttendanceDeviceSyncLog;
use App\Models\AttendanceDeviceUser;
use App\Models\AttendanceRawEvent;
use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\Employee;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use App\Services\Attendance\Devices\DeviceSyncService;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Attendance devices: list with health, configuration, detail (sync state,
 * mappings, unknown device users, sync logs) and the operations: sync now,
 * connection test, historical import, push token, activate / deactivate.
 */
class DeviceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AttendanceDevice::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer'],
            'health' => ['nullable', 'string'],
        ]);

        $devices = AttendanceDevice::query()->with(['branch', 'location', 'latestSyncLog'])
            ->withCount(['users'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$term}%")->orWhereLike('serial_number', "%{$term}%")))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('branch_id', $id))
            ->orderBy('name')
            ->get();

        if ($health = $filters['health'] ?? null) {
            $devices = $devices->filter(fn (AttendanceDevice $device): bool => $device->health()->value === $health)->values();
        }

        return view('admin.attendance.devices.index', [
            'devices' => $devices,
            'filters' => $filters,
            'branches' => Branch::query()->orderBy('code')->get(),
            'healthOptions' => DeviceHealth::cases(),
            'unmatchedCount' => AttendanceRawEvent::query()->where('status', 'unmatched')->count(),
        ]);
    }

    public function create(DeviceAdapterRegistry $adapters): View
    {
        Gate::authorize('create', AttendanceDevice::class);

        return view('admin.attendance.devices.form', $this->formData(new AttendanceDevice([
            'adapter' => 'simulated', 'connection_mode' => DeviceConnectionMode::Pull, 'pull_interval_minutes' => 15, 'is_active' => true,
        ]), $adapters));
    }

    public function store(DeviceRequest $request, ManageAttendanceDevice $manage): RedirectResponse
    {
        Gate::authorize('create', AttendanceDevice::class);

        $result = $manage->create($request->validated(), $request->user());

        return redirect()->route('admin.attendance-devices.show', $result['device'])
            ->with('status', __('devices.flash.created'))
            ->with('device_token', $result['token']);
    }

    public function show(Request $request, AttendanceDevice $attendanceDevice): View
    {
        Gate::authorize('view', $attendanceDevice);

        $device = $attendanceDevice->load(['branch', 'location', 'creator']);
        $mappings = $device->users()->with('employee')->orderBy('device_user_id')->get();
        $globalMappings = AttendanceDeviceUser::query()->whereNull('attendance_device_id')->with('employee')
            ->whereHas('employee', fn (Builder $query) => $query->where('branch_id', $device->branch_id))->orderBy('device_user_id')->get();

        $unknownUsers = AttendanceDeviceEvent::query()
            ->where('attendance_device_id', $device->id)
            ->whereIn('attendance_raw_event_id', AttendanceRawEvent::query()->where('status', 'unmatched')->select('id'))
            ->groupBy('device_user_id')
            ->orderBy('device_user_id')
            ->get(['device_user_id', DB::raw('count(*) as events'), DB::raw('min(created_at) as first_seen'), DB::raw('max(created_at) as last_seen')]);

        return view('admin.attendance.devices.show', [
            'device' => $device,
            'health' => $device->health(),
            'mappings' => $mappings,
            'globalMappings' => $globalMappings,
            'unknownUsers' => $unknownUsers,
            'logs' => $device->syncLogs()->with('triggeredBy')->paginate(15)->withQueryString(),
            'employees' => Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'token' => session('device_token'),
            'pushUrl' => route('api.v1.devices.events', $device->serial_number),
            'eventsCount' => $device->events()->count(),
        ]);
    }

    public function edit(AttendanceDevice $attendanceDevice, DeviceAdapterRegistry $adapters): View
    {
        Gate::authorize('update', $attendanceDevice);

        return view('admin.attendance.devices.form', $this->formData($attendanceDevice, $adapters));
    }

    public function update(DeviceRequest $request, AttendanceDevice $attendanceDevice, ManageAttendanceDevice $manage): RedirectResponse
    {
        Gate::authorize('update', $attendanceDevice);

        $device = $manage->update($attendanceDevice, $request->validated());
        $token = $device->connection_mode === DeviceConnectionMode::Push && $device->push_token_hash === null
            ? $manage->rotateToken($device, $request->user())
            : null;

        return redirect()->route('admin.attendance-devices.show', $device)->with('status', __('devices.flash.updated'))->with('device_token', $token);
    }

    public function destroy(AttendanceDevice $attendanceDevice): RedirectResponse
    {
        Gate::authorize('delete', $attendanceDevice);

        $attendanceDevice->delete();

        return redirect()->route('admin.attendance-devices.index')->with('status', __('devices.flash.deleted'));
    }

    public function status(Request $request, AttendanceDevice $attendanceDevice, ManageAttendanceDevice $manage): RedirectResponse
    {
        Gate::authorize('update', $attendanceDevice);

        $manage->setActive($attendanceDevice, ! $attendanceDevice->is_active);

        return back()->with('status', __($attendanceDevice->is_active ? 'devices.flash.activated' : 'devices.flash.deactivated'));
    }

    public function sync(Request $request, AttendanceDevice $attendanceDevice, DeviceSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage', $attendanceDevice);
        abort_unless($attendanceDevice->is_active && $attendanceDevice->connection_mode === DeviceConnectionMode::Pull, 422);

        $log = $sync->pull($attendanceDevice, $request->user());
        $audit->log('attendance_device.synced', $attendanceDevice, meta: ['sync_log_id' => $log->id, 'status' => $log->status->value, 'created' => $log->created]);

        return back()->with($log->status->value === 'failed' ? 'error' : 'status', $this->summary($log));
    }

    public function test(Request $request, AttendanceDevice $attendanceDevice, DeviceSyncService $sync): RedirectResponse
    {
        Gate::authorize('manage', $attendanceDevice);

        $log = $sync->test($attendanceDevice, $request->user());

        return back()->with($log->status->value === 'failed' ? 'error' : 'status', __('devices.flash.tested', ['message' => $log->message]));
    }

    public function historical(Request $request, AttendanceDevice $attendanceDevice, DeviceSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manage', $attendanceDevice);
        abort_unless($attendanceDevice->is_active && $attendanceDevice->connection_mode === DeviceConnectionMode::Pull, 422);

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
        ]);
        $timezone = $attendanceDevice->effectiveTimezone();
        $from = CarbonImmutable::parse($data['from'], $timezone)->utc();
        $to = CarbonImmutable::parse($data['to'], $timezone)->endOfDay()->utc();

        if ($from->diffInDays($to) > (int) config('attendance_devices.max_historical_days')) {
            throw ValidationException::withMessages(['to' => __('devices.historical.too_long', ['days' => config('attendance_devices.max_historical_days')])]);
        }

        $log = $sync->historical($attendanceDevice, $from, $to, $request->user());
        $audit->log('attendance_device.historical_import', $attendanceDevice, meta: ['from' => $data['from'], 'to' => $data['to'], 'sync_log_id' => $log->id]);

        return back()->with($log->status->value === 'failed' ? 'error' : 'status', $this->summary($log));
    }

    public function token(Request $request, AttendanceDevice $attendanceDevice, ManageAttendanceDevice $manage): RedirectResponse
    {
        Gate::authorize('manage', $attendanceDevice);
        abort_unless($attendanceDevice->connection_mode === DeviceConnectionMode::Push, 422);

        return back()->with('status', __('devices.flash.token_rotated'))->with('device_token', $manage->rotateToken($attendanceDevice, $request->user()));
    }

    private function summary(AttendanceDeviceSyncLog $log): string
    {
        return $log->status->value === 'failed'
            ? __('devices.flash.sync_failed', ['message' => $log->message])
            : __('devices.flash.synced', ['created' => $log->created, 'duplicates' => $log->duplicates, 'unmatched' => $log->unmatched]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(AttendanceDevice $device, DeviceAdapterRegistry $adapters): array
    {
        return [
            'device' => $device,
            'branches' => Branch::query()->activeOrKeep($device->branch_id)->orderBy('code')->get(),
            'locations' => BranchLocation::query()->activeOrKeep($device->branch_location_id)->orderBy('id')->get(),
            'adapters' => collect($adapters->keys())->map(fn (string $key): array => ['key' => $key, 'simulated' => $adapters->isSimulated($key), 'modes' => $adapters->modes($key)]),
            'modes' => DeviceConnectionMode::cases(),
            'timezones' => \DateTimeZone::listIdentifiers(),
        ];
    }
}
