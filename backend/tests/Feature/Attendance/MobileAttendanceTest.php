<?php

use App\Enums\AttendanceDayStatus;
use App\Enums\AttendanceScope;
use App\Enums\MobileAttemptOutcome;
use App\Enums\RawEventSource;
use App\Enums\ReviewStatus;
use App\Enums\WorkLocationScope;
use App\Models\AttendanceDay;
use App\Models\AttendanceMobileAttempt;
use App\Models\AttendanceRawEvent;
use App\Models\AttendanceWorkLocationRule;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
| Mobile GPS check-in / check-out (Phase 5b): server-side validation, the
| review queue, work locations and the API used by the Flutter app.
| 2026-11-01 is a Sunday (a working day of workWeek()); Cairo is UTC+2.
*/

const MOBILE_LAT = 30.0285310;
const MOBILE_LNG = 31.4725180;

beforeEach(function () {
    seedRoles();
    seedAttendance();
    attendancePolicy(['face_verification_mode' => 'off', 'gps_max_accuracy_meters' => 50, 'gps_accuracy_tolerance_meters' => 20,
        'gps_max_time_drift_seconds' => 300, 'gps_mock_location_action' => 'review', 'gps_outside_window_action' => 'review',
        'gps_repeated_attempts_threshold' => 3]);

    $this->branch = Branch::factory()->create(['code' => 'CAI', 'timezone' => 'Africa/Cairo']);
    $this->department = Department::factory()->create(['code' => 'SAL', 'branch_id' => $this->branch->id]);
    $this->location = BranchLocation::factory()->create([
        'branch_id' => $this->branch->id, 'latitude' => MOBILE_LAT, 'longitude' => MOBILE_LNG, 'radius_meters' => 150,
    ]);
    $this->shift = Shift::factory()->between('08:00', '16:00')->create(['code' => 'MOR']);
    assignSchedule(workWeek($this->shift), AttendanceScope::Company, null);

    $this->employee = attendanceEmployee(['branch_id' => $this->branch->id, 'department_id' => $this->department->id]);
    $this->account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->api = fn () => $this->withToken($this->account->createToken('test')->plainTextToken);

    Carbon::setTestNow(Carbon::parse('2026-11-01 08:03', 'Africa/Cairo'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * A check-in body; $north moves the point north by that many metres.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function mobilePayload(float $north = 30, array $overrides = []): array
{
    return [
        'request_uuid' => (string) Str::uuid(),
        'latitude' => MOBILE_LAT + $north / 111_195,
        'longitude' => MOBILE_LNG,
        'accuracy' => 10,
        'is_mock_location' => false,
        'device_time' => now()->toIso8601String(),
        'device_id' => 'pixel-9-test',
        'platform' => 'android',
        'app_version' => '1.0.0',
        ...$overrides,
    ];
}

it('accepts a check-in inside the geofence and records it through the raw event pipeline at server time', function () {
    $response = ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(40, ['device_time' => now()->subSeconds(20)->toIso8601String()]));

    $response->assertCreated()
        ->assertJsonPath('data.outcome', 'accepted')
        ->assertJsonPath('data.recorded', true)
        ->assertJsonPath('data.location.within_geofence', true)
        ->assertJsonPath('data.location.id', $this->location->id)
        ->assertJsonPath('data.within_shift_window', true);

    $attempt = AttendanceMobileAttempt::query()->sole();
    $event = AttendanceRawEvent::query()->sole();

    expect($event->source)->toBe(RawEventSource::MobileGps)
        ->and($event->idempotency_key)->toBe('mobile:'.$attempt->request_uuid)
        ->and($event->occurred_at->toIso8601ZuluString())->toBe('2026-11-01T06:03:00Z')
        ->and($event->origin->is($attempt))->toBeTrue()
        ->and($attempt->attendance_raw_event_id)->toBe($event->id)
        ->and($attempt->distance_meters)->toBeBetween(38, 42)
        ->and($attempt->time_drift_seconds)->toBe(-20)
        ->and(AttendanceDay::query()->sole()->first_in_at->toIso8601ZuluString())->toBe('2026-11-01T06:03:00Z');
});

it('validates distance with the accuracy tolerance and rejects poor accuracy or points outside the geofence', function (float $north, float $accuracy, string $outcome, ?string $reason) {
    $response = ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload($north, ['accuracy' => $accuracy]));

    $attempt = AttendanceMobileAttempt::query()->sole();
    expect($attempt->outcome->value)->toBe($outcome);

    if ($reason === null) {
        $response->assertCreated();
        expect(AttendanceRawEvent::query()->count())->toBe(1);
    } else {
        $response->assertUnprocessable()->assertJsonPath('code', 'attendance_rejected')->assertJsonPath('data.reasons.0.code', $reason);
        expect(AttendanceRawEvent::query()->count())->toBe(0)
            ->and($attempt->reasonCodes())->toContain($reason);
    }
})->with([
    'inside the radius' => [100, 10, 'accepted', null],
    'outside the radius, credited by accuracy' => [160, 15, 'accepted', null],
    'tolerance is capped' => [175, 45, 'rejected', 'outside_geofence'],
    'far away' => [2000, 10, 'rejected', 'outside_geofence'],
    'accuracy worse than the policy maximum' => [10, 80, 'rejected', 'poor_accuracy'],
]);

it('sends suspicious attempts to review instead of recording them', function (array $overrides, string $reason, ?string $at = null) {
    if ($at !== null) {
        Carbon::setTestNow(Carbon::parse($at, 'Africa/Cairo'));
    }

    ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(20, $overrides))
        ->assertStatus(202)
        ->assertJsonPath('data.outcome', 'flagged')
        ->assertJsonPath('data.recorded', false);

    $attempt = AttendanceMobileAttempt::query()->sole();
    expect($attempt->review_status)->toBe(ReviewStatus::Pending)
        ->and($attempt->reasonCodes())->toContain($reason)
        ->and(AttendanceRawEvent::query()->count())->toBe(0);
})->with([
    'mock location' => [['is_mock_location' => true], 'mock_location'],
    'phone clock 20 minutes off' => [['device_time' => '2026-11-01T08:23:00+02:00'], 'time_drift'],
    'outside the shift window' => [[], 'outside_shift_window', '2026-11-01 04:30'],
    'rest day' => [[], 'outside_shift_window', '2026-11-06 09:00'],
]);

it('rejects instead of reviewing when the policy says so', function () {
    attendancePolicy(['face_verification_mode' => 'off', 'gps_mock_location_action' => 'reject', 'gps_outside_window_action' => 'reject']);

    ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(20, ['is_mock_location' => true]))
        ->assertUnprocessable()->assertJsonPath('data.reasons.0.code', 'mock_location');

    Carbon::setTestNow(Carbon::parse('2026-11-01 03:00', 'Africa/Cairo'));
    ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(20))
        ->assertUnprocessable()->assertJsonPath('data.reasons.0.code', 'outside_shift_window');
});

it('flags the next attempt after repeated rejections', function () {
    foreach (range(1, 3) as $ignored) {
        ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(5000))->assertUnprocessable();
    }

    ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(20))->assertStatus(202);

    expect(AttendanceMobileAttempt::query()->latest('id')->first()->reasonCodes())->toBe(['repeated_attempts']);
});

it('is idempotent per request uuid and refuses a uuid of another employee', function () {
    $payload = mobilePayload(20);

    ($this->api)()->postJson('/api/v1/me/attendance/check-in', $payload)->assertCreated();
    ($this->api)()->postJson('/api/v1/me/attendance/check-in', $payload)->assertCreated()->assertJsonPath('data.request_uuid', $payload['request_uuid']);

    expect(AttendanceMobileAttempt::query()->count())->toBe(1)->and(AttendanceRawEvent::query()->count())->toBe(1);

    $other = User::factory()->employee()->create(['employee_id' => attendanceEmployee(['branch_id' => $this->branch->id, 'department_id' => $this->department->id])->id]);
    $this->withToken($other->createToken('t')->plainTextToken)->postJson('/api/v1/me/attendance/check-in', $payload)->assertStatus(409);
});

it('lets a reviewer approve (recorded at the attempt time) or reject a flagged attempt, audited, never their own', function () {
    ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(20, ['is_mock_location' => true]))->assertStatus(202);
    $first = AttendanceMobileAttempt::query()->sole();

    Carbon::setTestNow(Carbon::parse('2026-11-01 11:00', 'Africa/Cairo'));
    $officer = adminWithRoles('Attendance Officer');

    $this->actingAs($officer, 'admin')->get(route('admin.attendance.reviews.index'))->assertOk()->assertSee($this->employee->employee_number);
    $this->actingAs($officer, 'admin')->post(route('admin.attendance.reviews.decide', [$first, 'approve']), ['note' => 'Known phone issue'])
        ->assertRedirect(route('admin.attendance.reviews.index'));

    $first->refresh();
    expect($first->review_status)->toBe(ReviewStatus::Approved)
        ->and($first->rawEvent->occurred_at->toIso8601ZuluString())->toBe('2026-11-01T06:03:00Z')
        ->and($first->rawEvent->recorded_by)->toBe($officer->id)
        ->and(AuditLog::query()->where('event', 'attendance.mobile_attempt_approved')->exists())->toBeTrue();

    // Deciding twice is refused.
    $this->actingAs($officer, 'admin')->post(route('admin.attendance.reviews.decide', [$first, 'reject']), ['note' => 'x'])->assertForbidden();

    Carbon::setTestNow(Carbon::parse('2026-11-01 16:05', 'Africa/Cairo'));
    ($this->api)()->postJson('/api/v1/me/attendance/check-out', mobilePayload(20, ['is_mock_location' => true]))->assertStatus(202);
    $second = AttendanceMobileAttempt::query()->latest('id')->first();

    $this->actingAs($officer, 'admin')->post(route('admin.attendance.reviews.decide', [$second, 'reject']), [])->assertSessionHasErrors('note');
    $this->actingAs($officer, 'admin')->post(route('admin.attendance.reviews.decide', [$second, 'reject']), ['note' => 'Not on site'])->assertRedirect();

    expect($second->fresh()->review_status)->toBe(ReviewStatus::Rejected)
        ->and($second->fresh()->attendance_raw_event_id)->toBeNull();

    // A reviewer linked to the employee profile cannot decide on their own attempt.
    ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(20, ['is_mock_location' => true]))->assertStatus(202);
    $own = AttendanceMobileAttempt::query()->latest('id')->first();
    $self = adminWithRoles('HR Manager');
    $self->forceFill(['employee_id' => $this->employee->id])->save();
    $this->actingAs($self, 'admin')->post(route('admin.attendance.reviews.decide', [$own, 'approve']))->assertForbidden();

    // Without attendance.approve the queue is visible but not decidable.
    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->post(route('admin.attendance.reviews.decide', [$own, 'approve']))->assertForbidden();
});

it('uses work-location rules: employee override, department rule and remote work', function () {
    $other = BranchLocation::factory()->create(['branch_id' => Branch::factory()->create()->id, 'latitude' => 31.2, 'longitude' => 29.9, 'radius_meters' => 100]);
    $atOther = mobilePayload(0, ['latitude' => 31.2, 'longitude' => 29.9]);

    ($this->api)()->postJson('/api/v1/me/attendance/check-in', $atOther)->assertUnprocessable();

    AttendanceWorkLocationRule::query()->create(['scope_type' => WorkLocationScope::Department, 'scope_id' => $this->department->id, 'branch_location_id' => $other->id]);
    ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(0, ['latitude' => 31.2, 'longitude' => 29.9]))->assertCreated();
    // The department rule replaces the branch default.
    ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(20))->assertUnprocessable();

    AttendanceWorkLocationRule::query()->create(['scope_type' => WorkLocationScope::Employee, 'scope_id' => $this->employee->id, 'allow_anywhere' => true,
        'effective_from' => '2026-11-01', 'effective_to' => '2026-11-30']);
    ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(0, ['latitude' => 25.0, 'longitude' => 33.0]))->assertCreated()
        ->assertJsonPath('data.location.allow_anywhere', true);

    $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')->get(route('admin.attendance.work-locations.index'))->assertOk()
        ->assertSee($this->employee->employee_number);
    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.attendance.work-locations.index'))->assertForbidden();
});

it('returns today\'s state for the attendance action', function () {
    ($this->api)()->getJson('/api/v1/me/attendance/today')->assertOk()
        ->assertJsonPath('data.date', '2026-11-01')
        ->assertJsonPath('data.state', 'not_checked_in')
        ->assertJsonPath('data.next_action', 'check_in')
        ->assertJsonPath('data.within_shift_window', true)
        ->assertJsonPath('data.shifts.0.code', 'MOR')
        ->assertJsonPath('data.locations.source', 'branch')
        ->assertJsonPath('data.locations.items.0.id', $this->location->id)
        ->assertJsonPath('data.rules.max_accuracy_meters', 50)
        ->assertJsonPath('data.face.mode', 'off')
        ->assertJsonPath('data.face.is_simulated', true);

    ($this->api)()->postJson('/api/v1/me/attendance/check-in', mobilePayload(20))->assertCreated();

    ($this->api)()->getJson('/api/v1/me/attendance/today')->assertOk()
        ->assertJsonPath('data.state', 'checked_in')
        ->assertJsonPath('data.next_action', 'check_out')
        ->assertJsonPath('data.punches.0.source', 'mobile_gps')
        ->assertJsonPath('data.days.0.status', AttendanceDayStatus::Present->value);

    ($this->api)()->getJson('/api/v1/me/attendance/attempts')->assertOk()->assertJsonCount(1, 'data');
});

it('accepts employee tokens only and validates the request', function () {
    $this->postJson('/api/v1/me/attendance/check-in', mobilePayload())->assertUnauthorized();

    $admin = User::factory()->admin()->create(['employee_id' => $this->employee->id]);
    $this->withToken($admin->createToken('x')->plainTextToken)->postJson('/api/v1/me/attendance/check-in', mobilePayload())->assertForbidden();

    ($this->api)()->postJson('/api/v1/me/attendance/check-in', ['latitude' => 120])->assertUnprocessable()
        ->assertJsonValidationErrors(['request_uuid', 'latitude', 'longitude', 'accuracy']);

    expect(AttendanceMobileAttempt::query()->count())->toBe(0);
});

it('runs scenario 2: login, face enrolment, GPS + face check-in, processed day visible to HR', function () {
    attendancePolicy(['face_verification_mode' => 'required', 'face_max_attempts' => 3, 'face_failure_action' => 'review']);
    $this->account->forceFill(['email' => 'mobile@example.test', 'password' => 'password'])->save();

    // 1. The employee opens the app and signs in.
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'mobile@example.test', 'password' => 'password', 'device_name' => 'Pixel 9'])
        ->assertOk()->json('data.token');
    $api = fn () => $this->withToken($token);

    // Face enrolment (simulated provider, labelled as such) after consent.
    $api()->getJson('/api/v1/me/face')->assertOk()->assertJsonPath('data.is_simulated', true)->assertJsonPath('data.enrolled', false);
    $api()->postJson('/api/v1/me/face/consent', ['accepted' => true, 'version' => setting('face_verification.consent_version')])->assertCreated();
    $api()->postJson('/api/v1/me/face/enrol', ['token' => 'sim-enrol', 'liveness_passed' => true])->assertCreated()->assertJsonPath('data.enrolled', true);

    // 2-4. Check-in: GPS validated against the assigned location, face verified.
    $api()->getJson('/api/v1/me/attendance/today')->assertOk()->assertJsonPath('data.face.mode', 'required')->assertJsonPath('data.face.enrolled', true);
    $api()->postJson('/api/v1/me/attendance/check-in', mobilePayload(25, ['face' => ['token' => 'sim-match', 'liveness_passed' => true]]))
        ->assertCreated()
        ->assertJsonPath('data.outcome', 'accepted')
        ->assertJsonPath('data.location.within_geofence', true)
        ->assertJsonPath('data.face.result', 'matched')
        ->assertJsonPath('data.face.is_simulated', true);

    // 5. The backend stored and processed the attendance.
    $day = AttendanceDay::query()->where('employee_id', $this->employee->id)->sole();
    expect($day->status)->toBe(AttendanceDayStatus::Present)
        ->and($day->sources)->toBe(['mobile_gps']);

    // 6. HR sees it on the attendance dashboard and the daily list.
    $hr = adminWithRoles('HR Manager');
    $this->actingAs($hr, 'admin')->get(route('admin.attendance.dashboard', ['date' => '2026-11-01']))
        ->assertOk()->assertSee($this->employee->name)->assertSee('data-count="present"', false);
    $this->actingAs($hr, 'admin')->get(route('admin.attendance.daily', ['date' => '2026-11-01', 'source' => 'mobile_gps']))
        ->assertOk()->assertSee($this->employee->employee_number);
    expect(AttendanceMobileAttempt::query()->sole()->outcome)->toBe(MobileAttemptOutcome::Accepted);
});
