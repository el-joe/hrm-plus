<?php

use App\Actions\Attendance\ManageAttendanceDevice;
use App\Enums\AttendanceScope;
use App\Enums\DeviceHealth;
use App\Enums\DeviceSyncStatus;
use App\Enums\DeviceSyncType;
use App\Enums\RawEventSource;
use App\Enums\RawEventStatus;
use App\Jobs\SyncAttendanceDevice;
use App\Models\AttendanceDay;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceEvent;
use App\Models\AttendanceDeviceSyncLog;
use App\Models\AttendanceDeviceUser;
use App\Models\AttendanceRawEvent;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Shift;
use App\Services\Attendance\Devices\DeviceMappingService;
use App\Services\Attendance\Devices\DeviceSyncService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

/*
| Attendance devices (Phase 5b): push endpoint, pull sync with the simulated
| adapter, failure handling, mappings / unmatched events, CSV fallback import,
| permissions and data scope. 2026-11-01 is a Sunday; Cairo is UTC+2.
*/

beforeEach(function () {
    seedRoles();
    seedAttendance();
    attendancePolicy();

    $this->cairo = Branch::factory()->create(['code' => 'CAI', 'timezone' => 'Africa/Cairo']);
    $this->alex = Branch::factory()->create(['code' => 'ALX', 'timezone' => 'Africa/Cairo']);
    $department = Department::factory()->create(['branch_id' => $this->cairo->id]);
    $shift = Shift::factory()->between('08:00', '16:00')->create(['code' => 'MOR']);
    assignSchedule(workWeek($shift), AttendanceScope::Company, null);

    $this->employee = attendanceEmployee(['employee_number' => 'E-100', 'branch_id' => $this->cairo->id, 'department_id' => $department->id]);
    $this->admin = superAdmin();
});

afterEach(fn () => Carbon::setTestNow());

/**
 * A device created through the real action (push devices get a token).
 *
 * @param  array<string, mixed>  $attributes
 * @return array{device: AttendanceDevice, token: ?string}
 */
function makeDevice(array $attributes = []): array
{
    return app(ManageAttendanceDevice::class)->create([
        'name' => 'Gate', 'serial_number' => 'SN-'.uniqid(), 'branch_id' => test()->cairo->id, 'adapter' => 'simulated',
        'connection_mode' => 'push', 'pull_interval_minutes' => 15, 'is_active' => true, ...$attributes,
    ], test()->admin);
}

it('accepts pushed logs only with the device token and stores them idempotently', function () {
    ['device' => $device, 'token' => $token] = makeDevice();
    app(DeviceMappingService::class)->map($device, '100', $this->employee, $this->admin);
    $url = route('api.v1.devices.events', $device->serial_number);
    $body = ['events' => [
        ['user_id' => '100', 'time' => '2026-11-01 07:58:12', 'direction' => 'in', 'log_id' => 'L1'],
        ['user_id' => '100', 'time' => '2026-11-01T16:02:00+02:00', 'direction' => 'out', 'log_id' => 'L2'],
        ['user_id' => '555', 'time' => '2026-11-01 08:10:00'],
    ]];

    $this->postJson($url, $body)->assertUnauthorized()->assertJsonPath('code', 'device_unauthorised');
    $this->withToken('hrmd_wrong')->postJson($url, $body)->assertUnauthorized();
    $this->withToken($token)->postJson(route('api.v1.devices.events', 'UNKNOWN-1'), $body)->assertUnauthorized();

    $this->withToken($token)->postJson($url, $body)->assertOk()
        ->assertJsonPath('data.received', 3)
        ->assertJsonPath('data.created', 3)
        ->assertJsonPath('data.unmatched', 1)
        ->assertJsonPath('data.simulated', true);

    // Re-sent batch: duplicates, nothing new (unique idempotency key at DB level).
    $this->withToken($token)->postJson($url, $body)->assertOk()->assertJsonPath('data.created', 0)->assertJsonPath('data.duplicates', 3);

    expect(AttendanceRawEvent::query()->count())->toBe(3)
        ->and(AttendanceRawEvent::query()->where('source', RawEventSource::Device)->whereNotNull('employee_id')->pluck('idempotency_key')->all())
        ->toContain('device:'.$device->serial_number.':100:'.Carbon::parse('2026-11-01 05:58:12', 'UTC')->getTimestamp())
        ->and(AttendanceDeviceEvent::query()->count())->toBe(3)
        ->and(AttendanceDeviceSyncLog::query()->where('type', DeviceSyncType::Push)->count())->toBe(2);

    $day = AttendanceDay::query()->where('employee_id', $this->employee->id)->whereDate('work_date', '2026-11-01')->sole();
    expect($day->first_in_at->toIso8601ZuluString())->toBe('2026-11-01T05:58:12Z')
        ->and($day->last_out_at->toIso8601ZuluString())->toBe('2026-11-01T14:02:00Z');

    // Invalid payloads are refused and logged; a deactivated device is refused.
    $this->withToken($token)->postJson($url, ['events' => [['time' => 'x']]])->assertUnprocessable()->assertJsonPath('code', 'invalid_payload');
    expect(AttendanceDeviceSyncLog::query()->where('status', DeviceSyncStatus::Failed)->count())->toBe(1);

    $device->forceFill(['is_active' => false])->save();
    $this->withToken($token)->postJson($url, $body)->assertForbidden();
});

it('stores only a hash of the push token and invalidates it on rotation', function () {
    ['device' => $device, 'token' => $token] = makeDevice();

    expect($device->push_token_hash)->toBe(hash('sha256', $token))
        ->and($device->fresh()->toArray())->not->toHaveKey('push_token_hash');

    $this->actingAs($this->admin, 'admin')->post(route('admin.attendance-devices.token', $device))->assertSessionHas('device_token');
    $this->withToken($token)->postJson(route('api.v1.devices.events', $device->serial_number), ['events' => [['user_id' => '1', 'time' => '2026-11-01 08:00']]])
        ->assertUnauthorized();
});

it('pulls the simulated device, logs the run, moves the cursor and reports re-pulled logs as duplicates', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-01 17:00', 'Africa/Cairo'));
    // A fixed serial keeps the simulated logs deterministic.
    ['device' => $device] = makeDevice(['connection_mode' => 'pull', 'serial_number' => 'PULL-TEST-1']);
    $device->forceFill(['sync_cursor' => '2026-10-31T22:00:00Z'])->save();
    app(DeviceMappingService::class)->map($device, '100', $this->employee, $this->admin);
    app(DeviceMappingService::class)->map($device, '101', attendanceEmployee(['branch_id' => $this->cairo->id, 'department_id' => $this->employee->department_id]), $this->admin);

    $log = app(DeviceSyncService::class)->pull($device, $this->admin);

    expect($log->status)->toBe(DeviceSyncStatus::Success)
        ->and($log->is_simulated)->toBeTrue()
        ->and($log->created)->toBeGreaterThan(0)
        ->and($log->fetched)->toBe($log->created)
        ->and(AttendanceRawEvent::query()->count())->toBe($log->created)
        ->and($device->fresh()->sync_cursor)->toBe('2026-11-01T15:00:00Z')
        ->and($device->fresh()->health())->toBe(DeviceHealth::Ok);

    // A historical import over the same window finds the same logs: duplicates only.
    $again = app(DeviceSyncService::class)->historical($device, Carbon::parse('2026-10-31T22:00:00Z')->toImmutable(), Carbon::parse('2026-11-01T15:00:00Z')->toImmutable());
    expect($again->created)->toBe(0)->and($again->duplicates)->toBe($log->created)
        ->and($device->fresh()->sync_cursor)->toBe('2026-11-01T15:00:00Z');
});

it('records failed pulls, backs off and marks the device as failing', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-01 10:00', 'UTC'));
    ['device' => $device] = makeDevice(['connection_mode' => 'pull', 'simulate_failure' => true]);
    $sync = app(DeviceSyncService::class);

    $first = $sync->pull($device);
    expect($first->status)->toBe(DeviceSyncStatus::Failed)
        ->and($first->message)->toBe(__('devices.simulated.failure'))
        ->and($device->fresh()->consecutive_failures)->toBe(1)
        ->and($device->fresh()->next_retry_at->toIso8601ZuluString())->toBe('2026-11-01T10:05:00Z')
        ->and($device->fresh()->health())->toBe(DeviceHealth::Warning)
        ->and($device->fresh()->isDueForPull())->toBeFalse();

    $sync->pull($device->fresh());
    $third = $sync->pull($device->fresh());

    expect($third->attempt)->toBe(3)
        ->and($device->fresh()->next_retry_at->toIso8601ZuluString())->toBe('2026-11-01T10:20:00Z')
        ->and($device->fresh()->health())->toBe(DeviceHealth::Error);

    // Recovery resets the failure counter.
    $device->forceFill(['options' => null])->save();
    expect($sync->pull($device->fresh())->status)->toBe(DeviceSyncStatus::Success)
        ->and($device->fresh()->consecutive_failures)->toBe(0)
        ->and($device->fresh()->health())->toBe(DeviceHealth::Ok);
});

it('schedules pulls only for active pull devices that are due', function () {
    Queue::fake();
    ['device' => $due] = makeDevice(['connection_mode' => 'pull']);
    ['device' => $recent] = makeDevice(['connection_mode' => 'pull']);
    $recent->forceFill(['last_sync_at' => now()])->save();
    makeDevice(['connection_mode' => 'push']);
    makeDevice(['connection_mode' => 'pull', 'is_active' => false]);

    $this->artisan('attendance:sync-devices')->assertSuccessful();

    Queue::assertPushed(SyncAttendanceDevice::class, 1);
    Queue::assertPushed(SyncAttendanceDevice::class, fn (SyncAttendanceDevice $job) => $job->deviceId === $due->id);
});

it('keeps unknown device users as unmatched events until they are mapped, then processes them', function () {
    ['device' => $device, 'token' => $token] = makeDevice();
    $this->withToken($token)->postJson(route('api.v1.devices.events', $device->serial_number), ['events' => [
        ['user_id' => '777', 'time' => '2026-11-01 08:01:00', 'direction' => 'in'],
        ['user_id' => '777', 'time' => '2026-11-01 16:00:00', 'direction' => 'out'],
    ]])->assertOk()->assertJsonPath('data.unmatched', 2);

    expect(AttendanceRawEvent::query()->where('status', RawEventStatus::Unmatched)->count())->toBe(2)
        ->and(AttendanceDay::query()->count())->toBe(0);

    $this->actingAs($this->admin, 'admin')->get(route('admin.attendance.events.index', ['state' => 'unmatched']))
        ->assertOk()->assertSee(__('devices.events.device_user', ['id' => '777']));
    $this->actingAs($this->admin, 'admin')->get(route('admin.attendance-devices.show', $device))->assertOk()->assertSee('777');

    $this->actingAs($this->admin, 'admin')->post(route('admin.attendance-devices.mappings.store', $device), [
        'scope' => 'device', 'rows' => [['device_user_id' => '777', 'employee_id' => $this->employee->id]],
    ])->assertRedirect()->assertSessionHas('status');

    expect(AttendanceRawEvent::query()->where('status', RawEventStatus::Unmatched)->count())->toBe(0)
        ->and(AttendanceDeviceUser::query()->sole()->employee_id)->toBe($this->employee->id)
        ->and(AttendanceDay::query()->where('employee_id', $this->employee->id)->whereDate('work_date', '2026-11-01')->sole()->worked_minutes)->toBeGreaterThan(400);

    // New logs of that user are matched directly.
    $this->withToken($token)->postJson(route('api.v1.devices.events', $device->serial_number), ['events' => [['user_id' => '777', 'time' => '2026-11-02 08:00:00']]])
        ->assertJsonPath('data.unmatched', 0);
});

it('maps a single unmatched event from the review screen, optionally remembering the device user', function () {
    ['device' => $device, 'token' => $token] = makeDevice();
    $this->withToken($token)->postJson(route('api.v1.devices.events', $device->serial_number), ['events' => [
        ['user_id' => '42', 'time' => '2026-11-01 08:00:00', 'direction' => 'in'],
        ['user_id' => '42', 'time' => '2026-11-01 16:00:00', 'direction' => 'out'],
    ]]);
    [$first, $second] = AttendanceRawEvent::query()->orderBy('id')->get()->all();

    $this->actingAs($this->admin, 'admin')->post(route('admin.attendance.events.match', $first), ['employee_id' => $this->employee->id])->assertRedirect();
    expect($first->fresh()->employee_id)->toBe($this->employee->id)
        ->and($second->fresh()->status)->toBe(RawEventStatus::Unmatched)
        ->and(AttendanceDeviceUser::query()->count())->toBe(0);

    $this->actingAs($this->admin, 'admin')->post(route('admin.attendance.events.match', $second), ['employee_id' => $this->employee->id, 'remember' => 1])->assertRedirect();
    expect($second->fresh()->employee_id)->toBe($this->employee->id)
        ->and(AttendanceDeviceUser::query()->sole()->device_user_id)->toBe('42');

    // Unmatched events can also be voided.
    $this->withToken($token)->postJson(route('api.v1.devices.events', $device->serial_number), ['events' => [['user_id' => '99', 'time' => '2026-11-01 09:00:00']]]);
    $stray = AttendanceRawEvent::query()->where('status', RawEventStatus::Unmatched)->sole();
    $this->actingAs($this->admin, 'admin')->post(route('admin.attendance.events.void', $stray), ['reason' => 'Test card'])->assertRedirect();
    expect($stray->fresh()->status)->toBe(RawEventStatus::Void);
});

it('imports a CSV device log with a dry run that writes nothing first', function () {
    ['device' => $device] = makeDevice(['connection_mode' => 'file']);
    app(DeviceMappingService::class)->map($device, '100', $this->employee, $this->admin);
    $csv = "device_user_id,time,direction,log_id\n100,2026-11-01 08:04:00,in,A1\n100,2026-11-01 16:01:00,out,A2\n100,2026-11-01 16:01:00,out,A2\n888,2026-11-01 08:30:00,,\n";
    $file = fn () => UploadedFile::fake()->createWithContent('export.csv', $csv);

    $response = $this->actingAs($this->admin, 'admin')->post(route('admin.attendance-devices.import.analyse', $device), ['file' => $file()])->assertOk();
    $report = $response->viewData('report');

    expect($report['total'])->toBe(4)
        ->and($report['new'])->toBe(2)
        ->and($report['unmatched'])->toBe(1)
        ->and($report['duplicates'])->toBe(1)
        ->and($report['errors'])->toBe([])
        ->and(AttendanceRawEvent::query()->count())->toBe(0);

    $this->actingAs($this->admin, 'admin')->post(route('admin.attendance-devices.import.store', [$device, $response->viewData('token')]), ['file_name' => 'export.csv'])
        ->assertRedirect(route('admin.attendance-devices.show', $device));

    $log = AttendanceDeviceSyncLog::query()->where('type', DeviceSyncType::File)->sole();
    expect(AttendanceRawEvent::query()->count())->toBe(3)
        ->and($log->created)->toBe(3)
        ->and($log->unmatched)->toBe(1)
        ->and($log->file_name)->toBe('export.csv')
        ->and(AttendanceDay::query()->where('employee_id', $this->employee->id)->whereDate('work_date', '2026-11-01')->sole()->first_in_at->toIso8601ZuluString())->toBe('2026-11-01T06:04:00Z');

    // Rows with errors block the import.
    $bad = UploadedFile::fake()->createWithContent('bad.csv', "device_user_id,time,direction\n100,not-a-time,in\n100,2026-11-01 09:00,sideways\n");
    $report = $this->actingAs($this->admin, 'admin')->post(route('admin.attendance-devices.import.analyse', $device), ['file' => $bad])->viewData('report');
    expect(collect($report['errors'])->pluck('column')->all())->toBe(['time', 'direction']);
});

it('enforces permissions and the branch scope on devices', function () {
    ['device' => $cairo] = makeDevice();
    ['device' => $alex] = makeDevice(['branch_id' => $this->alex->id]);

    $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->get(route('admin.attendance-devices.index'))->assertForbidden();
    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->post(route('admin.attendance-devices.token', $cairo))->assertForbidden();

    $officer = adminWithRoles('Attendance Officer');
    $this->actingAs($officer, 'admin')->get(route('admin.attendance-devices.show', $cairo))->assertOk()->assertSee(__('devices.simulated.badge'));
    $this->actingAs($officer, 'admin')->get(route('admin.attendance-devices.create'))->assertForbidden();

    $scoped = adminWithRoles('Branch Manager');
    $scoped->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->alex->id]);
    $this->actingAs($scoped, 'admin')->get(route('admin.attendance-devices.index'))->assertOk()->assertSee($alex->serial_number)->assertDontSee($cairo->serial_number);
    $this->actingAs($scoped, 'admin')->get(route('admin.attendance-devices.show', $cairo))->assertNotFound();
});

it('creates and edits a device from the admin form, keeping stored credentials when left empty', function () {
    $this->actingAs($this->admin, 'admin')->post(route('admin.attendance-devices.store'), [
        'name' => 'Lobby', 'serial_number' => 'LOBBY-1', 'branch_id' => $this->cairo->id, 'adapter' => 'simulated', 'connection_mode' => 'pull',
        'pull_interval_minutes' => 10, 'credentials' => 's3cret', 'is_active' => 1,
    ])->assertRedirect();

    $device = AttendanceDevice::query()->where('serial_number', 'LOBBY-1')->sole();
    expect($device->credentials)->toBe('s3cret')
        ->and($device->getRawOriginal('credentials'))->not->toBe('s3cret');

    $this->actingAs($this->admin, 'admin')->put(route('admin.attendance-devices.update', $device), [
        'name' => 'Lobby 2', 'serial_number' => 'LOBBY-1', 'branch_id' => $this->cairo->id, 'adapter' => 'simulated', 'connection_mode' => 'pull',
        'pull_interval_minutes' => 10, 'credentials' => '', 'is_active' => 1,
    ])->assertRedirect();

    expect($device->fresh()->name)->toBe('Lobby 2')->and($device->fresh()->credentials)->toBe('s3cret');

    $this->actingAs($this->admin, 'admin')->post(route('admin.attendance-devices.store'), [
        'name' => 'X', 'serial_number' => 'LOBBY-1', 'adapter' => 'zkteco', 'connection_mode' => 'pull', 'pull_interval_minutes' => 10,
    ])->assertSessionHasErrors(['serial_number', 'adapter']);
});
