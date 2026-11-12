<?php

use App\Actions\Training\ManageTrainingEnrolment;
use App\Enums\TrainingEnrolmentSource;
use App\Enums\TrainingEnrolmentStatus;
use App\Models\AuditLog;
use App\Models\Skill;
use App\Models\TrainingCertificate;
use App\Models\TrainingCourse;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

require_once __DIR__.'/TrainingHelpers.php';

/*
| Admin training screens: permissions (training.view / create / update / approve /
| export / manage), data scope on enrolments and certificates, catalogue guards,
| the session workflow over HTTP (nominate → attendance → outcomes), decisions,
| exports and the employee profile tab.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    seedTraining();
    Notification::fake();
    $this->hr = adminWithRoles('HR Manager');
    $this->employee = attendanceEmployee();
});

describe('permissions', function () {
    it('opens every training list for training.view and refuses admins without it', function () {
        $viewer = adminWithPermissions('training.view');
        $session = trainingSession(by: $this->hr);

        foreach (['admin.training-sessions.index', 'admin.training-enrolments.index', 'admin.training-courses.index', 'admin.training-certificates.index',
            'admin.skills-matrix', 'admin.training-budgets.index', 'admin.training-providers.index', 'admin.training-programmes.index', 'admin.skills.index'] as $route) {
            $this->actingAs($viewer, 'admin')->get(route($route))->assertOk();
            $this->actingAs(adminWithRoles('Reports Viewer'), 'admin')->get(route($route))->assertForbidden();
        }

        $this->actingAs($viewer, 'admin')->get(route('admin.training-sessions.show', $session))->assertOk();
    });

    it('keeps the catalogue, sessions and budgets to training.manage', function () {
        $specialist = adminWithRoles('HR Specialist');

        $this->actingAs($specialist, 'admin')->get(route('admin.training-sessions.create'))->assertForbidden();
        $this->actingAs($specialist, 'admin')->get(route('admin.training-courses.create'))->assertForbidden();
        $this->actingAs($specialist, 'admin')->post(route('admin.training-budgets.store'), ['year' => 2026, 'amount' => '100'])->assertForbidden();
        $this->actingAs($this->hr, 'admin')->get(route('admin.training-sessions.create'))->assertOk();
    });

    it('sends a nomination by a manager without training.manage for approval', function () {
        $session = trainingSession(by: $this->hr);

        $this->actingAs(adminWithRoles('HR Specialist'), 'admin')
            ->post(route('admin.training-sessions.nominate', $session), ['employee_ids' => [$this->employee->id]])->assertRedirect();

        $enrolment = TrainingEnrolment::query()->sole();
        expect($enrolment->status)->toBe(TrainingEnrolmentStatus::Pending)->and($enrolment->source)->toBe(TrainingEnrolmentSource::Manager);

        $this->actingAs($this->hr, 'admin')->post(route('admin.training-enrolments.decide', [$enrolment, 'approve']))->assertRedirect();
        expect($enrolment->refresh()->status)->toBe(TrainingEnrolmentStatus::Enrolled);
    });

    it('requires a reason to reject and refuses decisions to admins without training.approve', function () {
        $session = trainingSession(by: $this->hr);
        $enrolment = app(ManageTrainingEnrolment::class)->nominate($session, $this->employee, adminWithRoles('HR Specialist'));

        $this->actingAs(adminWithPermissions('training.view'), 'admin')->post(route('admin.training-enrolments.decide', [$enrolment, 'approve']))->assertForbidden();
        $this->actingAs($this->hr, 'admin')->post(route('admin.training-enrolments.decide', [$enrolment, 'reject']), [])->assertSessionHasErrors('reason');
    });
});

describe('data scope', function () {
    it('shows a branch-scoped admin only the participants and certificates of their branch', function () {
        $session = trainingSession(by: $this->hr);
        $inside = enrolEmployee($session, $this->employee, $this->hr);
        $outsider = attendanceEmployee();
        $outside = enrolEmployee($session, $outsider, $this->hr);
        $theirCertificate = TrainingCertificate::factory()->create(['employee_id' => $outsider->id, 'name' => 'Outside cert']);
        $manager = adminWithPermissions('training.view', 'training.update');
        $manager->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->employee->branch_id]);

        $this->actingAs($manager, 'admin')->get(route('admin.training-sessions.show', $session))
            ->assertOk()->assertSee($this->employee->employee_number)->assertDontSee($outsider->employee_number);
        $this->actingAs($manager, 'admin')->get(route('admin.training-enrolments.show', $inside))->assertOk();
        $this->actingAs($manager, 'admin')->get(route('admin.training-enrolments.show', $outside))->assertNotFound();
        $this->actingAs($manager, 'admin')->get(route('admin.training-certificates.index'))->assertOk()->assertDontSee('Outside cert');
        $this->actingAs($manager, 'admin')->get(route('admin.training-certificates.download', $theirCertificate))->assertNotFound();
    });
});

describe('catalogue', function () {
    it('creates a course with the skills it teaches', function () {
        $skill = Skill::factory()->create();

        $this->actingAs($this->hr, 'admin')->post(route('admin.training-courses.store'), [
            'code' => 'xl-1', 'name_ar' => 'إكسل', 'name_en' => 'Excel', 'delivery_mode' => 'online', 'duration_hours' => '6',
            'cost_per_participant' => '450.75', 'issues_certificate' => '1', 'certificate_validity_months' => 24, 'is_active' => '1',
            'skills' => [['skill_id' => $skill->id, 'level' => 4], ['skill_id' => '', 'level' => '']],
        ])->assertRedirect(route('admin.training-courses.index'));

        $course = TrainingCourse::query()->where('code', 'XL-1')->sole();
        expect($course->cost_per_participant)->toBe('450.75')
            ->and($course->courseSkills()->sole()->level)->toBe(4);
    });

    it('does not delete a course that has sessions (deactivate instead)', function () {
        $session = trainingSession(by: $this->hr);

        $this->actingAs($this->hr, 'admin')->delete(route('admin.training-courses.destroy', $session->course))->assertForbidden();
        $this->actingAs($this->hr, 'admin')->patch(route('admin.training-courses.status', $session->course), ['is_active' => 0])->assertRedirect();

        expect($session->course->refresh()->is_active)->toBeFalse();
    });
});

describe('session workflow over HTTP', function () {
    it('validates the session form (online sessions need a link)', function () {
        $course = TrainingCourse::factory()->create();

        $this->actingAs($this->hr, 'admin')->post(route('admin.training-sessions.store'), [
            'training_course_id' => $course->id, 'start_date' => '2026-12-01', 'end_date' => '2026-11-30', 'delivery_mode' => 'online', 'capacity' => 0,
        ])->assertSessionHasErrors(['end_date', 'meeting_url', 'capacity']);

        $this->actingAs($this->hr, 'admin')->post(route('admin.training-sessions.store'), [
            'training_course_id' => $course->id, 'start_date' => '2026-12-01', 'end_date' => '2026-12-02', 'delivery_mode' => 'online',
            'meeting_url' => 'https://meet.example/x', 'capacity' => 5,
        ])->assertRedirect(route('admin.training-sessions.show', TrainingSession::query()->sole()));
    });

    it('nominates, records attendance and outcomes, which issue the certificate', function () {
        $course = TrainingCourse::factory()->withCertificate(24)->create();
        $session = trainingSession(['start_date' => '2026-11-16', 'end_date' => '2026-11-16'], $course, $this->hr);

        $this->actingAs($this->hr, 'admin')->post(route('admin.training-sessions.nominate', $session), ['employee_ids' => [$this->employee->id]])
            ->assertSessionHasNoErrors();
        $enrolment = TrainingEnrolment::query()->sole();

        $this->travelTo(CarbonImmutable::parse('2026-11-16 17:00:00'));
        $this->actingAs($this->hr, 'admin')->post(route('admin.training-sessions.attendance', $session), [
            'date' => '2026-11-16', 'attendance' => [$enrolment->id => ['status' => 'present']],
        ])->assertRedirect();
        $this->actingAs($this->hr, 'admin')->post(route('admin.training-sessions.outcomes', $session), [
            'outcomes' => [$enrolment->id => ['status' => 'completed', 'score' => '91']],
        ])->assertSessionHasNoErrors();

        expect($enrolment->refresh()->status)->toBe(TrainingEnrolmentStatus::Completed)
            ->and(TrainingCertificate::query()->sole()->expires_on->toDateString())->toBe('2028-11-16')
            ->and(AuditLog::query()->where('event', 'training.outcome_recorded')->exists())->toBeTrue();
    });

    it('reports the employees that could not be nominated (session full)', function () {
        $session = trainingSession(['capacity' => 1], by: $this->hr);
        $second = attendanceEmployee();

        $this->actingAs($this->hr, 'admin')->post(route('admin.training-sessions.nominate', $session), ['employee_ids' => [$this->employee->id, $second->id]])
            ->assertSessionHasErrors('employee_ids');

        expect(TrainingEnrolment::query()->count())->toBe(1);
    });
});

describe('exports and profile', function () {
    it('exports enrolments and certificates with training.export', function () {
        enrolEmployee(trainingSession(by: $this->hr), $this->employee, $this->hr);
        TrainingCertificate::factory()->create(['employee_id' => $this->employee->id]);

        $this->actingAs($this->hr, 'admin')->get(route('admin.training-enrolments.export'))->assertOk()->assertDownload();
        $this->actingAs($this->hr, 'admin')->get(route('admin.training-certificates.export', ['format' => 'csv']))->assertOk()->assertDownload();
        $this->actingAs(adminWithPermissions('training.view'), 'admin')->get(route('admin.training-enrolments.export'))->assertForbidden();
    });

    it('shows the training tab on the employee profile to training viewers only', function () {
        TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'name' => 'Safety passport']);

        $this->actingAs($this->hr, 'admin')->get(route('admin.employees.tab', [$this->employee, 'training']))->assertOk()->assertSee('Safety passport');
        $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')->get(route('admin.employees.tab', [$this->employee, 'training']))->assertForbidden();
    });

    it('adds completed courses and certificates to the history timeline', function () {
        TrainingCertificate::factory()->create(['employee_id' => $this->employee->id, 'name' => 'Safety passport', 'issued_on' => '2026-01-10']);

        $this->actingAs($this->hr, 'admin')->get(route('admin.employees.tab', [$this->employee, 'history']))
            ->assertOk()->assertSee(__('training.timeline.certificate', ['name' => 'Safety passport']));
    });
});
