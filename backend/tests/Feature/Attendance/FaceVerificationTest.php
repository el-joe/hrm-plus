<?php

use App\Actions\Employees\TerminateEmployee;
use App\Enums\AttendanceScope;
use App\Enums\FaceEnrolmentStatus;
use App\Enums\FaceVerificationResult;
use App\Enums\ReviewStatus;
use App\Models\AttendanceMobileAttempt;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\FaceConsent;
use App\Models\FaceEnrolment;
use App\Models\FaceVerification;
use App\Models\Shift;
use App\Models\User;
use App\Services\Attendance\Face\BiometricStore;
use App\Services\Attendance\Face\Data\FaceSample;
use App\Services\Attendance\Face\FaceEnrolmentService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/*
| Face verification (Phase 5b): the provider boundary with the SIMULATED
| provider (outcome chosen by the token), modes off / optional / required,
| retry and fallback rules, consent, encrypted storage, deletion and retention.
*/

beforeEach(function () {
    Storage::fake('biometric_files');
    seedRoles();
    seedAttendance();

    $branch = Branch::factory()->create(['timezone' => 'Africa/Cairo']);
    BranchLocation::factory()->create(['branch_id' => $branch->id, 'latitude' => 30.0, 'longitude' => 31.0, 'radius_meters' => 200]);
    assignSchedule(workWeek(Shift::factory()->between('08:00', '16:00')->create()), AttendanceScope::Company, null);

    $this->employee = attendanceEmployee(['branch_id' => $branch->id]);
    $this->account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->faces = app(FaceEnrolmentService::class);

    Carbon::setTestNow(Carbon::parse('2026-11-01 08:00', 'Africa/Cairo'));
});

afterEach(fn () => Carbon::setTestNow());

function faceEnrol(): FaceEnrolment
{
    $service = app(FaceEnrolmentService::class);
    $service->giveConsent(test()->employee, 'api', test()->account, null);

    return $service->enrol(test()->employee, new FaceSample('sim-enrol', livenessPassed: true), test()->account);
}

/**
 * @param  array<string, mixed>|null  $face
 */
function faceCheckIn(?array $face, string $endpoint = 'check-in'): TestResponse
{
    return test()->withToken(test()->account->createToken('t')->plainTextToken)->postJson('/api/v1/me/attendance/'.$endpoint, [
        'request_uuid' => (string) Str::uuid(), 'latitude' => 30.0001, 'longitude' => 31.0, 'accuracy' => 8,
        'device_time' => now()->toIso8601String(), ...($face !== null ? ['face' => $face] : []),
    ]);
}

it('skips face verification when the policy turns it off', function () {
    attendancePolicy(['face_verification_mode' => 'off']);

    faceCheckIn(null)->assertCreated()->assertJsonPath('data.face.mode', 'off')->assertJsonPath('data.face.result', null);
    expect(FaceVerification::query()->count())->toBe(0);
});

it('verifies when a face is sent in optional mode and never accepts a failure silently', function () {
    attendancePolicy(['face_verification_mode' => 'optional', 'face_failure_action' => 'review']);
    faceEnrol();

    faceCheckIn(null)->assertCreated()->assertJsonPath('data.face.result', 'not_provided');
    faceCheckIn(['token' => 'sim-match'])->assertCreated()->assertJsonPath('data.face.result', 'matched');
    faceCheckIn(['token' => 'sim-no-match'])->assertStatus(202)->assertJsonPath('data.reasons.0.code', 'face_failed');
});

it('requires a face in required mode, allows retries, then falls back to review', function () {
    attendancePolicy(['face_verification_mode' => 'required', 'face_max_attempts' => 2, 'face_failure_action' => 'review']);
    faceEnrol();

    faceCheckIn(null)->assertUnprocessable()->assertJsonPath('data.reasons.0.code', 'face_required');
    faceCheckIn(['token' => 'sim-no-match'])->assertUnprocessable()
        ->assertJsonPath('data.reasons.0.code', 'face_retry')
        ->assertJsonPath('data.face_remaining_attempts', 1);
    faceCheckIn(['token' => 'sim-no-match'])->assertStatus(202)->assertJsonPath('data.reasons.0.code', 'face_failed');

    // Attempts are used up for today: no provider call, straight to the manual fallback.
    faceCheckIn(['token' => 'sim-match'])->assertStatus(202);

    expect(FaceVerification::query()->where('result', FaceVerificationResult::NotMatched)->count())->toBe(2)
        ->and(FaceVerification::query()->pluck('attempt_number')->all())->toBe([1, 2])
        ->and(AttendanceMobileAttempt::query()->where('review_status', ReviewStatus::Pending)->count())->toBe(2);

    // The check-out direction has its own attempts.
    Carbon::setTestNow(Carbon::parse('2026-11-01 16:00', 'Africa/Cairo'));
    faceCheckIn(['token' => 'sim-match'], 'check-out')->assertCreated();
});

it('treats a failed liveness check or provider error as a failure and can reject instead of reviewing', function () {
    attendancePolicy(['face_verification_mode' => 'required', 'face_max_attempts' => 1, 'face_failure_action' => 'reject']);
    faceEnrol();

    faceCheckIn(['token' => 'sim-liveness-fail'])->assertUnprocessable()->assertJsonPath('data.reasons.0.code', 'face_failed');
    expect(FaceVerification::query()->sole()->result)->toBe(FaceVerificationResult::LivenessFailed);

    attendancePolicy(['face_verification_mode' => 'required', 'face_max_attempts' => 5, 'face_failure_action' => 'review']);
    faceCheckIn(['token' => 'sim-error'])->assertUnprocessable()->assertJsonPath('data.reasons.0.code', 'face_retry');
    expect(FaceVerification::query()->latest('id')->first()->result)->toBe(FaceVerificationResult::ProviderError);
});

it('sends employees who are not enrolled to the manual fallback', function () {
    attendancePolicy(['face_verification_mode' => 'required', 'face_failure_action' => 'review']);

    faceCheckIn(['token' => 'sim-match'])->assertStatus(202)->assertJsonPath('data.reasons.0.code', 'face_not_enrolled');

    $attempt = AttendanceMobileAttempt::query()->sole();
    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->post(route('admin.attendance.reviews.decide', [$attempt, 'approve']), ['note' => 'Verified in person'])
        ->assertRedirect();
    expect($attempt->fresh()->attendance_raw_event_id)->not->toBeNull();
});

it('records consent per text version, enrols through the API and stores the template encrypted on the private disk', function () {
    $api = $this->withToken($this->account->createToken('t')->plainTextToken);

    $api->postJson('/api/v1/me/face/enrol', ['token' => 'sim-enrol'])->assertUnprocessable()->assertJsonValidationErrors('face');
    $api->postJson('/api/v1/me/face/consent', ['accepted' => true, 'version' => '0.9'])->assertUnprocessable();
    $api->postJson('/api/v1/me/face/consent', ['accepted' => true, 'version' => setting('face_verification.consent_version')])->assertCreated()
        ->assertJsonPath('data.consent.given', true);
    $api->postJson('/api/v1/me/face/enrol', ['token' => 'sim-enrol', 'liveness_passed' => true])->assertCreated()
        ->assertJsonPath('data.enrolled', true)->assertJsonPath('data.is_simulated', true);

    $enrolment = FaceEnrolment::query()->sole();
    $stored = Storage::disk('biometric_files')->get($enrolment->template_path);

    expect($enrolment->is_simulated)->toBeTrue()
        ->and($stored)->not->toContain('SIMULATED-TEMPLATE')
        ->and(app(BiometricStore::class)->get($enrolment->template_path))->toStartWith('SIMULATED-TEMPLATE:')
        ->and($enrolment->getRawOriginal('provider_reference'))->not->toStartWith('sim-')
        ->and($enrolment->toArray())->not->toHaveKeys(['template_path', 'provider_reference'])
        ->and(AuditLog::query()->where('event', 'face.enrolled')->sole()->new_values)->toBeNull();

    // A new consent text version asks the employee again.
    app(SettingsService::class)->set('face_verification.consent_version', '2.0');
    $api->getJson('/api/v1/me/face')->assertOk()->assertJsonPath('data.consent.given', false)->assertJsonPath('data.consent.current_version', '2.0');
});

it('deletes biometric data when consent is withdrawn', function () {
    $enrolment = faceEnrol();
    $path = $enrolment->template_path;

    $this->withToken($this->account->createToken('t')->plainTextToken)->deleteJson('/api/v1/me/face/consent')->assertOk()
        ->assertJsonPath('data.enrolled', false);

    $enrolment->refresh();
    expect($enrolment->status)->toBe(FaceEnrolmentStatus::Deleted)
        ->and($enrolment->deletion_reason)->toBe('consent_withdrawn')
        ->and($enrolment->template_path)->toBeNull()
        ->and($enrolment->getRawOriginal('provider_reference'))->toBeNull()
        ->and(Storage::disk('biometric_files')->exists($path))->toBeFalse()
        ->and(FaceConsent::query()->sole()->withdrawn_at)->not->toBeNull();
});

it('deletes biometric data when a termination takes effect', function () {
    $enrolment = faceEnrol();

    app(TerminateEmployee::class)->handle($this->employee, ['termination_date' => '2026-11-01', 'termination_type' => 'resignation', 'reason' => 'Moving abroad']);

    expect($enrolment->fresh()->status)->toBe(FaceEnrolmentStatus::Deleted)
        ->and($enrolment->fresh()->deletion_reason)->toBe('termination');
});

it('purges enrolments and verification results past their retention period', function () {
    $old = faceEnrol();
    $old->forceFill(['enrolled_at' => now()->subDays(800)])->save();
    FaceVerification::query()->create([
        'employee_id' => $this->employee->id, 'provider' => 'simulated', 'is_simulated' => true, 'direction' => 'in',
        'result' => 'matched', 'work_date' => '2025-01-01', 'verified_at' => now()->subDays(400),
    ]);
    FaceVerification::query()->create([
        'employee_id' => $this->employee->id, 'provider' => 'simulated', 'is_simulated' => true, 'direction' => 'in',
        'result' => 'matched', 'work_date' => '2026-10-30', 'verified_at' => now()->subDays(2),
    ]);

    $this->artisan('face:purge')->assertSuccessful();

    expect($old->fresh()->status)->toBe(FaceEnrolmentStatus::Deleted)
        ->and($old->fresh()->deletion_reason)->toBe('retention')
        ->and(FaceVerification::query()->count())->toBe(1);
});

it('shows the enrolment register only with permission, logs each view and lets HR delete the data', function () {
    $enrolment = faceEnrol();

    $this->actingAs(adminWithRoles('Payroll Accountant'), 'admin')->get(route('admin.attendance.face.index'))->assertForbidden();

    $officer = adminWithRoles('Attendance Officer');
    $this->actingAs($officer, 'admin')->get(route('admin.attendance.face.index'))->assertOk()
        ->assertSee($this->employee->employee_number)->assertSee(__('devices.simulated.badge'));
    expect(AuditLog::query()->where('event', 'sensitive_access.face_enrolments')->where('user_id', $officer->id)->exists())->toBeTrue();
    $this->actingAs($officer, 'admin')->delete(route('admin.attendance.face.destroy', $enrolment), ['reason' => 'x'])->assertForbidden();

    $this->actingAs(adminWithRoles('HR Manager'), 'admin')->delete(route('admin.attendance.face.destroy', $enrolment), ['reason' => 'Employee request'])->assertRedirect();
    expect($enrolment->fresh()->status)->toBe(FaceEnrolmentStatus::Deleted)
        ->and($enrolment->fresh()->deletion_reason)->toBe('admin');
});
