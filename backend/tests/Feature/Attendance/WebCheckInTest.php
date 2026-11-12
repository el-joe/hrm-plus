<?php

use App\Enums\AttendanceScope;
use App\Enums\CheckInChannel;
use App\Enums\MobileAttemptOutcome;
use App\Enums\RawEventSource;
use App\Enums\ReviewStatus;
use App\Models\AttendanceDay;
use App\Models\AttendanceMobileAttempt;
use App\Models\AttendanceRawEvent;
use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\Department;
use App\Models\Shift;
use App\Models\User;
use App\Services\Attendance\Face\Data\FaceSample;
use App\Services\Attendance\Face\FaceEnrolmentService;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\ReportRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
| Check-in / check-out from the employee web portal: the same validation as the
| app (MobileCheckIn, channel "web"), the policy switches, the portal card and
| the admin labels. 2026-11-01 is a Sunday (a working day); Cairo is UTC+2.
*/

const WEB_LAT = 30.0285310;
const WEB_LNG = 31.4725180;

beforeEach(function () {
    Storage::fake('biometric_files');
    seedRoles();
    seedAttendance();
    attendancePolicy(['face_verification_mode' => 'off', 'gps_max_accuracy_meters' => 50, 'gps_accuracy_tolerance_meters' => 20,
        'gps_outside_window_action' => 'review', 'gps_repeated_attempts_threshold' => 3]);

    $this->branch = Branch::factory()->create(['code' => 'CAI', 'timezone' => 'Africa/Cairo']);
    $this->department = Department::factory()->create(['code' => 'SAL', 'branch_id' => $this->branch->id]);
    $this->location = BranchLocation::factory()->create([
        'branch_id' => $this->branch->id, 'name_en' => 'Cairo HQ', 'latitude' => WEB_LAT, 'longitude' => WEB_LNG, 'radius_meters' => 150,
    ]);
    assignSchedule(workWeek(Shift::factory()->between('08:00', '16:00')->create(['code' => 'MOR'])), AttendanceScope::Company, null);

    $this->employee = attendanceEmployee(['branch_id' => $this->branch->id, 'department_id' => $this->department->id]);
    $this->account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);

    Carbon::setTestNow(Carbon::parse('2026-11-01 08:03', 'Africa/Cairo'));
});

afterEach(fn () => Carbon::setTestNow());

/**
 * A browser check-in body; $north moves the point north by that many metres.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function webPayload(float $north = 30, array $overrides = []): array
{
    return [
        'request_uuid' => (string) Str::uuid(),
        'latitude' => WEB_LAT + $north / 111_195,
        'longitude' => WEB_LNG,
        'accuracy' => 10,
        'device_time' => now()->toIso8601String(),
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $payload
 */
function webCheckIn(array $payload, string $endpoint = 'check-in', ?User $as = null): TestResponse
{
    return test()->actingAs($as ?? test()->account, 'employee')
        ->withSession(['locale' => 'en'])
        ->withHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) Firefox/140.0')
        ->postJson(route('portal.attendance.'.$endpoint), $payload);
}

function webEnrolFace(): void
{
    $faces = app(FaceEnrolmentService::class);
    $faces->giveConsent(test()->employee, 'api', test()->account, null);
    $faces->enrol(test()->employee, new FaceSample('sim-enrol', livenessPassed: true), test()->account);
}

it('records a web check-in and check-out as web_portal events at server time and processes the day', function () {
    webCheckIn(webPayload(40))->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.outcome', 'accepted')
        ->assertJsonPath('data.channel', 'web')
        ->assertJsonPath('data.recorded', true)
        ->assertJsonPath('data.location.within_geofence', true);

    $attempt = AttendanceMobileAttempt::query()->sole();
    $event = AttendanceRawEvent::query()->sole();

    expect($attempt->channel)->toBe(CheckInChannel::Web)
        ->and($attempt->platform)->toBe('web')
        ->and($attempt->device_identifier)->toContain('Firefox')
        ->and($attempt->is_mock_location)->toBeFalse()
        ->and($event->source)->toBe(RawEventSource::WebPortal)
        ->and($event->idempotency_key)->toBe('web:'.$attempt->request_uuid)
        ->and($event->payload['channel'])->toBe('web')
        ->and($event->occurred_at->toIso8601ZuluString())->toBe('2026-11-01T06:03:00Z');

    Carbon::setTestNow(Carbon::parse('2026-11-01 16:05', 'Africa/Cairo'));
    webCheckIn(webPayload(20), 'check-out')->assertCreated()->assertJsonPath('message', __('mobile.api.accepted_out'));

    $day = AttendanceDay::query()->whereDate('work_date', '2026-11-01')->sole();
    expect($day->first_in_at->toIso8601ZuluString())->toBe('2026-11-01T06:03:00Z')
        ->and($day->last_out_at->toIso8601ZuluString())->toBe('2026-11-01T14:05:00Z')
        ->and($day->sources)->toBe(['web_portal']);
});

it('applies exactly the rules of the app API to the same input', function (float $north, float $accuracy, ?string $at, int $status, string $outcome, array $reasons) {
    if ($at !== null) {
        Carbon::setTestNow(Carbon::parse($at, 'Africa/Cairo'));
    }

    $api = $this->withToken($this->account->createToken('t')->plainTextToken)
        ->postJson('/api/v1/me/attendance/check-in', webPayload($north, ['accuracy' => $accuracy, 'platform' => 'android']));
    $this->app['auth']->forgetGuards();
    $web = webCheckIn(webPayload($north, ['accuracy' => $accuracy]));

    $api->assertStatus($status);
    $web->assertStatus($status);

    [$mobileAttempt, $webAttempt] = AttendanceMobileAttempt::query()->orderBy('id')->get()->all();

    expect($mobileAttempt->channel)->toBe(CheckInChannel::Mobile)
        ->and($webAttempt->channel)->toBe(CheckInChannel::Web)
        ->and($webAttempt->outcome->value)->toBe($outcome)
        ->and($mobileAttempt->outcome->value)->toBe($outcome)
        ->and($webAttempt->reasonCodes())->toBe($mobileAttempt->reasonCodes())
        ->and($webAttempt->reasonCodes())->toBe($reasons)
        ->and($web->json('message'))->toBe($api->json('message'))
        ->and($web->json('data.location.distance_meters'))->toBe($api->json('data.location.distance_meters'));
})->with([
    'inside the geofence' => [40, 10, null, 201, 'accepted', []],
    'outside the geofence' => [2000, 10, null, 422, 'rejected', ['outside_geofence']],
    'poor accuracy' => [10, 80, null, 422, 'rejected', ['poor_accuracy']],
    'outside the shift window' => [20, 10, '2026-11-01 04:30', 202, 'flagged', ['outside_shift_window']],
]);

it('answers the geofence rejection with the distance and the nearest location', function () {
    webCheckIn(webPayload(2000))->assertUnprocessable()
        ->assertJsonPath('code', 'attendance_rejected')
        ->assertJsonPath('message', __('mobile.reasons.outside_geofence'))
        ->assertJsonPath('data.reasons.0.code', 'outside_geofence')
        ->assertJsonPath('data.location.name', 'Cairo HQ')
        ->assertJsonPath('data.location.allowed_radius_meters', 160);

    expect(AttendanceRawEvent::query()->count())->toBe(0);
});

it('sends a flagged web attempt to the review queue, recorded as web_portal only once approved', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-01 04:30', 'Africa/Cairo'));

    webCheckIn(webPayload(20))->assertStatus(202)->assertJsonPath('data.outcome', 'flagged')->assertJsonPath('data.recorded', false);

    $attempt = AttendanceMobileAttempt::query()->sole();
    expect($attempt->review_status)->toBe(ReviewStatus::Pending)->and(AttendanceRawEvent::query()->count())->toBe(0);

    $officer = adminWithRoles('Attendance Officer');
    $this->actingAs($officer, 'admin')->get(route('admin.attendance.reviews.index'))->assertOk()
        ->assertSee('data-channel="web"', false)->assertSee(__('mobile.enums.channels.web'));
    $this->actingAs($officer, 'admin')->post(route('admin.attendance.reviews.decide', [$attempt, 'approve']), ['note' => 'Was at the gate'])->assertRedirect();

    $event = AttendanceRawEvent::query()->sole();
    expect($event->source)->toBe(RawEventSource::WebPortal)
        ->and($event->idempotency_key)->toBe('web:'.$attempt->request_uuid)
        ->and($event->occurred_at->toIso8601ZuluString())->toBe($attempt->server_time->toIso8601ZuluString());
});

it('applies the face rules on the web: off, optional and required with retries and the review fallback', function () {
    $image = base64_encode('jpeg-bytes');

    // Off: no face needed.
    webCheckIn(webPayload())->assertCreated();

    // Required without a face: refused.
    attendancePolicy(['face_verification_mode' => 'required', 'face_max_attempts' => 2, 'face_failure_action' => 'review']);
    webCheckIn(webPayload(), 'check-out')->assertUnprocessable()->assertJsonPath('data.reasons.0.code', 'face_required');

    // Required, not enrolled, photo sent: manual fallback (review).
    webCheckIn(webPayload(20, ['face' => ['images' => [$image]]]), 'check-out')->assertStatus(202)
        ->assertJsonPath('data.reasons.0.code', 'face_not_enrolled');

    // Required and enrolled: a failed match leaves attempts, a camera photo matches (simulated provider).
    webEnrolFace();
    webCheckIn(webPayload(20, ['face' => ['token' => 'sim-no-match']]), 'check-out')->assertUnprocessable()
        ->assertJsonPath('data.reasons.0.code', 'face_retry')->assertJsonPath('data.face_remaining_attempts', 1);
    webCheckIn(webPayload(20, ['face' => ['images' => [$image], 'liveness_passed' => true]]), 'check-out')->assertCreated()
        ->assertJsonPath('data.face.result', 'matched');

    // Optional: no face is fine.
    attendancePolicy(['face_verification_mode' => 'optional']);
    Carbon::setTestNow(Carbon::parse('2026-11-01 09:00', 'Africa/Cairo'));
    webCheckIn(webPayload())->assertCreated()->assertJsonPath('data.face.mode', 'optional');

    // The browser cannot vouch for liveness: the flag is ignored, never stored as passed by the client.
    $verification = AttendanceMobileAttempt::query()->where('outcome', MobileAttemptOutcome::Accepted)->where('face_mode', 'required')->sole()->faceVerification;
    expect($verification->liveness_passed)->toBeTrue(); // decided by the (simulated) provider, not the request
});

it('is idempotent per request uuid and refuses a uuid used by another employee', function () {
    $payload = webPayload();

    webCheckIn($payload)->assertCreated();
    webCheckIn($payload)->assertCreated()->assertJsonPath('data.request_uuid', $payload['request_uuid']);

    expect(AttendanceMobileAttempt::query()->count())->toBe(1)->and(AttendanceRawEvent::query()->count())->toBe(1);

    $other = attendanceEmployee(['branch_id' => $this->branch->id, 'department_id' => $this->department->id]);
    $otherAccount = User::factory()->employee()->create(['employee_id' => $other->id]);

    webCheckIn($payload, as: $otherAccount)->assertStatus(409);

    // Each session only ever writes its own employee's attempts.
    webCheckIn(webPayload(), as: $otherAccount)->assertCreated();
    expect(AttendanceMobileAttempt::query()->where('employee_id', $other->id)->count())->toBe(1)
        ->and(AttendanceMobileAttempt::query()->where('employee_id', $this->employee->id)->count())->toBe(1)
        ->and(AttendanceRawEvent::query()->where('employee_id', $this->employee->id)->count())->toBe(1);
});

it('follows the policy switches for the web portal', function () {
    // Disallowed: refused on the server, explained on the portal with no button; the app is unaffected.
    attendancePolicy(['web_checkin_enabled' => false]);

    webCheckIn(webPayload())->assertUnprocessable()->assertJsonPath('data.reasons.0.code', 'web_disabled');
    $this->actingAs($this->account, 'employee')->get(route('portal.home'))->assertOk()
        ->assertSee('data-check-in-blocked="web_disabled"', false)->assertDontSee('data-check-in-submit', false);
    $this->actingAs($this->account, 'employee')->get(route('portal.attendance'))->assertOk()
        ->assertSee(__('mobile.web.blocked.web_disabled'))->assertDontSee('data-check-in-submit', false);
    $this->withToken($this->account->createToken('t')->plainTextToken)->postJson('/api/v1/me/attendance/check-in', webPayload())->assertCreated();
    $this->app['auth']->forgetGuards();

    // Allowed with the geofence (default): a position is required.
    attendancePolicy(['web_checkin_enabled' => true]);
    webCheckIn(['request_uuid' => (string) Str::uuid()], 'check-out')->assertUnprocessable()->assertJsonPath('data.reasons.0.code', 'location_required');

    // Geofence not required on the web: without a position, or far away, it is recorded (location kept for audit).
    attendancePolicy(['web_checkin_requires_geofence' => false]);
    webCheckIn(['request_uuid' => (string) Str::uuid()], 'check-out')->assertCreated()->assertJsonPath('data.location.accuracy_meters', null);
    webCheckIn(webPayload(5000, ['accuracy' => 900]), 'check-in')->assertCreated()->assertJsonPath('data.location.within_geofence', false);
});

it('shows the check-in card with the next action, window and face chips on the portal', function () {
    $this->actingAs($this->account, 'employee')->get(route('portal.home'))->assertOk()
        ->assertSee('data-check-in-submit', false)
        ->assertSee(__('mobile.web.check_in'))
        ->assertSee(__('mobile.web.chips.location_geofence'))
        ->assertSee(__('mobile.web.chips.face.off'))
        ->assertSee(route('portal.attendance.check-in'), false);

    webCheckIn(webPayload())->assertCreated();

    $this->actingAs($this->account, 'employee')->get(route('portal.attendance'))->assertOk()
        ->assertSee('data-attendance-state="checked_in"', false)
        ->assertSee(__('mobile.web.check_out'));
});

it('protects the web routes: CSRF, signed-in employees only, the browser permissions', function () {
    $this->postJson(route('portal.attendance.check-in'), webPayload())->assertUnauthorized();

    app()['env'] = 'production';
    $this->actingAs($this->account, 'employee')->postJson(route('portal.attendance.check-in'), webPayload())->assertStatus(419);
    app()['env'] = 'testing';

    expect(AttendanceMobileAttempt::query()->count())->toBe(0);

    $this->actingAs($this->account, 'employee')->get(route('portal.attendance'))
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=(), usb=()');
    $this->get(route('admin.login'))->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
});

it('labels and filters the web channel on the admin screens and reports', function () {
    webCheckIn(webPayload())->assertCreated();
    $this->withToken($this->account->createToken('t')->plainTextToken)->postJson('/api/v1/me/attendance/check-out', webPayload(10, ['platform' => 'ios']));
    $this->app['auth']->forgetGuards();
    Carbon::setTestNow(Carbon::parse('2026-11-01 04:30', 'Africa/Cairo'));
    webCheckIn(webPayload())->assertStatus(202);

    $admin = adminWithRoles('HR Manager');
    $web = __('attendance.enums.sources.web_portal');

    $reviews = $this->actingAs($admin, 'admin')->get(route('admin.attendance.reviews.index', ['view' => 'all', 'channel' => 'web']))->assertOk();
    expect($reviews->viewData('attempts')->pluck('channel')->unique()->all())->toBe([CheckInChannel::Web]);
    $this->actingAs($admin, 'admin')->get(route('admin.attendance.reviews.index', ['view' => 'all', 'channel' => 'mobile']))->assertOk()
        ->assertViewHas('attempts', fn ($attempts) => $attempts->count() === 1);

    $events = $this->actingAs($admin, 'admin')->get(route('admin.attendance.events.index', ['source' => 'web_portal']))->assertOk()->assertSee($web);
    expect($events->viewData('events')->pluck('source')->unique()->all())->toBe([RawEventSource::WebPortal]);

    $this->actingAs($admin, 'admin')->get(route('admin.attendance.daily', ['date' => '2026-11-01', 'source' => 'web_portal']))->assertOk()
        ->assertSee($web)->assertSee($this->employee->employee_number);
    $this->actingAs($admin, 'admin')->get(route('admin.attendance.dashboard'))->assertOk()->assertSee($web);

    $report = app(ReportRegistry::class)->find('attendance_source');
    $rows = collect(iterator_to_array(app(ReportRunner::class)->stream($report, app(ReportFilters::class)->resolve($report, ['from' => '2026-11-01', 'to' => '2026-11-01']), $admin), false))->keyBy('source');
    expect($rows[$web]['events'])->toBe(1)->and($rows[RawEventSource::MobileGps->label()]['events'])->toBe(1);
});
