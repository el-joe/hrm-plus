<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Enums\RawEventSource;
use App\Enums\RawEventStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceEvent;
use App\Models\AttendanceRawEvent;
use App\Models\Employee;
use App\Rules\ExistsInScope;
use App\Services\Attendance\Devices\DeviceMappingService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Raw event review: every attendance input as received (devices, mobile,
 * manual, import), filterable by state — unmatched device users, punches
 * the engine treated as duplicates, voided events — with the actions "map to
 * an employee" (optionally remembering the device user id) and "void".
 */
class RawEventController extends Controller
{
    public const STATES = ['unmatched', 'pending', 'processed', 'void', 'duplicate'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'state' => ['nullable', 'in:'.implode(',', self::STATES)],
            'source' => ['nullable', 'in:'.implode(',', array_column(RawEventSource::cases(), 'value'))],
            'device_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $timezone = (string) setting('localisation.timezone');

        $events = AttendanceRawEvent::query()
            ->with(['employee', 'punch', 'recorder'])
            ->when($filters['state'] ?? null, fn (Builder $query, string $state) => $state === 'duplicate'
                ? $query->whereHas('punch', fn (Builder $query) => $query->where('is_duplicate', true))
                : $query->where('status', $state))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
            ->when($filters['device_id'] ?? null, fn (Builder $query, $id) => $query->whereIn('id', AttendanceDeviceEvent::query()->where('attendance_device_id', $id)->select('attendance_raw_event_id')))
            ->when($filters['from'] ?? null, fn (Builder $query, string $date) => $query->where('occurred_at', '>=', CarbonImmutable::parse($date, $timezone)->utc()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $date) => $query->where('occurred_at', '<', CarbonImmutable::parse($date, $timezone)->addDay()->utc()))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->whereHas('employee', fn (Builder $query) => $query->search($term))
                ->orWhereLike('source_reference', "%{$term}%")))
            ->latest('occurred_at')->latest('id')
            ->paginate(30)->withQueryString();

        $deviceEvents = AttendanceDeviceEvent::query()->with('device')->whereIn('attendance_raw_event_id', $events->getCollection()->modelKeys())
            ->get()->keyBy('attendance_raw_event_id');

        return view('admin.attendance.events.index', [
            'events' => $events,
            'deviceEvents' => $deviceEvents,
            'filters' => $filters,
            'states' => self::STATES,
            'sources' => RawEventSource::cases(),
            'devices' => AttendanceDevice::query()->orderBy('name')->get(['id', 'name', 'serial_number', 'adapter']),
            'counts' => [
                'unmatched' => AttendanceRawEvent::query()->where('status', RawEventStatus::Unmatched)->count(),
                'pending' => AttendanceRawEvent::query()->where('status', RawEventStatus::Pending)->count(),
            ],
            'employees' => $request->user()->can('attendance.update')
                ? Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en'])
                : collect(),
        ]);
    }

    public function match(Request $request, AttendanceRawEvent $event, DeviceMappingService $mappings): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.update'), 403);
        abort_unless($event->status === RawEventStatus::Unmatched, 422);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', new ExistsInScope(Employee::class)],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $remember = $request->boolean('remember') && $request->user()->can('attendance_devices.manage');
        $matched = $mappings->matchEvent($event, Employee::query()->findOrFail($data['employee_id']), $remember, $request->user());

        return back()->with('status', trans_choice('devices.events.matched', $matched, ['count' => $matched]));
    }
}
