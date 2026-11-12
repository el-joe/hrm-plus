<?php

use App\Actions\Training\ManageTrainingEnrolment;
use App\Actions\Training\RecordTrainingAttendance;
use App\Actions\Training\SaveTrainingCourse;
use App\Actions\Training\SetEmployeeSkill;
use App\Enums\SkillSource;
use App\Enums\TrainingEnrolmentStatus;
use App\Models\EmployeeSkill;
use App\Models\Skill;
use App\Models\TrainingCertificate;
use App\Models\TrainingCourse;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/TrainingHelpers.php';

/*
| Attendance and completion: attendance per session day, the completion
| threshold (setting training.completion_min_attendance_percent), skills raised
| to the course levels (never lowered) and the course certificate with its expiry.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    seedTraining();
    Notification::fake();
    $this->hr = adminWithRoles('HR Manager');
    $this->manage = app(ManageTrainingEnrolment::class);
    $this->skill = Skill::factory()->create();
    $this->course = app(SaveTrainingCourse::class)->handle(null, [
        ...TrainingCourse::factory()->withCertificate(12)->raw(), 'code' => 'SAFE',
    ], [$this->skill->id => 3]);
    // Four days, 23–26 November.
    $this->session = trainingSession(['start_date' => '2026-11-23', 'end_date' => '2026-11-26'], $this->course, $this->hr);
    $this->employee = attendanceEmployee();
    $this->enrolment = enrolEmployee($this->session, $this->employee, $this->hr);
});

it('records attendance only on session days that have come', function () {
    $record = app(RecordTrainingAttendance::class);

    expect(fn () => $record->handle($this->session, '2026-11-23', [$this->enrolment->id => ['status' => 'present']], $this->hr))
        ->toThrow(ValidationException::class);

    $this->travelTo(CarbonImmutable::parse('2026-11-23 17:00:00'));

    expect(fn () => $record->handle($this->session, '2026-11-27', [$this->enrolment->id => ['status' => 'present']], $this->hr))
        ->toThrow(ValidationException::class);

    expect($record->handle($this->session, '2026-11-23', [$this->enrolment->id => ['status' => 'late', 'note' => 'Traffic']], $this->hr))->toBe(1)
        ->and($record->handle($this->session, '2026-11-23', [$this->enrolment->id => ['status' => 'present']], $this->hr))->toBe(1)
        ->and($this->enrolment->attendances()->count())->toBe(1)
        ->and($this->enrolment->attendances()->first()->status->value)->toBe('present');
});

it('computes the attendance rate over the session days (late counts, absent and excused do not)', function () {
    attendTraining($this->enrolment, ['2026-11-23'], 'present', $this->hr);
    attendTraining($this->enrolment, ['2026-11-24'], 'late', $this->hr);
    attendTraining($this->enrolment, ['2026-11-25'], 'excused', $this->hr);

    expect($this->enrolment->refresh()->load('attendances')->attendanceRate())->toBe('50.00');
});

it('refuses "completed" below the attendance threshold but accepts "failed"', function () {
    app(SettingsService::class)->set('training.completion_min_attendance_percent', 80);
    attendTraining($this->enrolment, ['2026-11-23', '2026-11-24', '2026-11-25'], 'present', $this->hr);
    attendTraining($this->enrolment, ['2026-11-26'], 'absent', $this->hr);

    expect(fn () => $this->manage->recordOutcome($this->enrolment->refresh(), TrainingEnrolmentStatus::Completed, '90', null, $this->hr))
        ->toThrow(ValidationException::class);

    $failed = $this->manage->recordOutcome($this->enrolment->refresh(), TrainingEnrolmentStatus::Failed, '40', 'Missed the exam day', $this->hr);

    expect($failed->status)->toBe(TrainingEnrolmentStatus::Failed)
        ->and($failed->score)->toBe('40.00')
        ->and(TrainingCertificate::query()->count())->toBe(0)
        ->and(EmployeeSkill::query()->count())->toBe(0);
});

it('completes: raises the skills and issues the certificate valid for the course months', function () {
    attendTraining($this->enrolment, ['2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26'], 'present', $this->hr);

    $done = $this->manage->recordOutcome($this->enrolment->refresh(), TrainingEnrolmentStatus::Completed, '88.5', null, $this->hr);
    $certificate = TrainingCertificate::query()->sole();
    $skill = EmployeeSkill::query()->sole();

    expect($done->status)->toBe(TrainingEnrolmentStatus::Completed)
        ->and($done->score)->toBe('88.50')
        ->and($certificate->employee_id)->toBe($this->employee->id)
        ->and($certificate->training_enrolment_id)->toBe($done->id)
        ->and($certificate->issued_on->toDateString())->toBe('2026-11-26')
        ->and($certificate->expires_on->toDateString())->toBe('2027-11-26')
        ->and($skill->level)->toBe(3)
        ->and($skill->source)->toBe(SkillSource::Training);
});

it('never lowers a skill level the employee already has', function () {
    app(SetEmployeeSkill::class)->handle($this->employee, $this->skill, 5, $this->hr);
    attendTraining($this->enrolment, ['2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26'], 'present', $this->hr);

    $this->manage->recordOutcome($this->enrolment->refresh(), TrainingEnrolmentStatus::Completed, null, null, $this->hr);

    expect(EmployeeSkill::query()->sole()->level)->toBe(5)
        ->and(EmployeeSkill::query()->sole()->source)->toBe(SkillSource::Manual);
});

it('does not record outcomes before the session starts or for pending places', function () {
    expect(fn () => $this->manage->recordOutcome($this->enrolment, TrainingEnrolmentStatus::NoShow, null, null, $this->hr))
        ->toThrow(ValidationException::class);
});

it('removes a manual skill with level 0', function () {
    app(SetEmployeeSkill::class)->handle($this->employee, $this->skill, 2, $this->hr);
    app(SetEmployeeSkill::class)->handle($this->employee, $this->skill, 0, $this->hr);

    expect(EmployeeSkill::query()->count())->toBe(0);
});
