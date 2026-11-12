<?php

use App\Actions\Training\ManageTrainingEnrolment;
use App\Actions\Training\ManageTrainingSession;
use App\Enums\ApprovalStatus;
use App\Enums\TrainingEnrolmentSource;
use App\Enums\TrainingEnrolmentStatus;
use App\Models\TrainingCourse;
use App\Models\User;
use App\Notifications\Training\TrainingEnrolmentNotification;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/TrainingHelpers.php';

/*
| Training enrolment (spec module 11): HR nominations are enrolled at once;
| manager nominations and employees' own requests go through the
| "training_enrolment" approval workflow; seats are never over-booked; cost and
| placement are snapshotted; cancellation and re-requests.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    seedTraining();
    Notification::fake();
    $this->hr = adminWithRoles('HR Manager');
    $this->manage = app(ManageTrainingEnrolment::class);
    $this->employee = attendanceEmployee();
    $this->session = trainingSession(by: $this->hr);
});

describe('nominations', function () {
    it('enrols an HR nomination at once with the cost and placement snapshot', function () {
        $enrolment = $this->manage->nominate($this->session, $this->employee, $this->hr);

        expect($enrolment->status)->toBe(TrainingEnrolmentStatus::Enrolled)
            ->and($enrolment->source)->toBe(TrainingEnrolmentSource::Hr)
            ->and($enrolment->cost)->toBe('1000.00')
            ->and($enrolment->department_id)->toBe($this->employee->department_id)
            ->and($enrolment->branch_id)->toBe($this->employee->branch_id)
            ->and($enrolment->approvalRequests()->count())->toBe(0);

        Notification::assertNothingSentTo($this->hr);
    });

    it('uses the session cost override instead of the course cost', function () {
        $session = trainingSession(['cost_per_participant' => '250.50'], by: $this->hr);

        expect($this->manage->nominate($session, $this->employee, $this->hr)->cost)->toBe('250.50');
    });

    it('sends a manager nomination for approval and enrols it when approved', function () {
        $manager = adminWithPermissions('training.view', 'training.create');
        $account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);

        $enrolment = $this->manage->nominate($this->session, $this->employee, $manager, 'Needs it for the new role');

        expect($enrolment->status)->toBe(TrainingEnrolmentStatus::Pending)
            ->and($enrolment->source)->toBe(TrainingEnrolmentSource::Manager)
            ->and($enrolment->latestApprovalRequest->status)->toBe(ApprovalStatus::Pending);
        Notification::assertSentTo($this->hr, TrainingEnrolmentNotification::class, fn ($notification) => $notification->kind === 'awaiting_approval');

        $this->manage->decide($enrolment->refresh(), $this->hr, true);

        expect($enrolment->refresh()->status)->toBe(TrainingEnrolmentStatus::Enrolled)
            ->and($enrolment->decided_by)->toBe($this->hr->id);
        Notification::assertSentTo($account, TrainingEnrolmentNotification::class, fn ($notification) => $notification->kind === 'approved');
    });

    it('refuses the same employee twice on a session', function () {
        $this->manage->nominate($this->session, $this->employee, $this->hr);

        $this->manage->nominate($this->session, $this->employee, $this->hr);
    })->throws(ValidationException::class);

    it('refuses cancelled and already started sessions', function () {
        app(ManageTrainingSession::class)->cancel($this->session, $this->hr, 'Trainer unavailable');

        expect(fn () => $this->manage->nominate($this->session->refresh(), $this->employee, $this->hr))->toThrow(ValidationException::class);

        $started = trainingSession(['start_date' => '2026-11-10', 'end_date' => '2026-11-13'], by: $this->hr);
        expect(fn () => $this->manage->nominate($started, attendanceEmployee(), $this->hr))->toThrow(ValidationException::class);
    });
});

describe('employee requests', function () {
    beforeEach(function () {
        $this->account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    });

    it('sends the own request for approval and records a rejection with its reason', function () {
        $enrolment = $this->manage->request($this->session, $this->employee, $this->account, 'Career growth');

        expect($enrolment->status)->toBe(TrainingEnrolmentStatus::Pending)->and($enrolment->source)->toBe(TrainingEnrolmentSource::Employee);

        $this->manage->decide($enrolment, $this->hr, false, 'Planned for next quarter');

        expect($enrolment->refresh()->status)->toBe(TrainingEnrolmentStatus::Rejected)
            ->and($enrolment->rejection_reason)->toBe('Planned for next quarter');
        Notification::assertSentTo($this->account, TrainingEnrolmentNotification::class, fn ($notification) => $notification->kind === 'rejected');
    });

    it('reuses the row when the employee asks again after a rejection', function () {
        $first = $this->manage->request($this->session, $this->employee, $this->account);
        $this->manage->decide($first, $this->hr, false, 'No');

        $second = $this->manage->request($this->session, $this->employee, $this->account);

        expect($second->id)->toBe($first->id)
            ->and($second->status)->toBe(TrainingEnrolmentStatus::Pending)
            ->and($second->rejection_reason)->toBeNull()
            ->and($second->approvalRequests()->count())->toBe(2);
    });

    it('refuses requests while the setting is off', function () {
        app(SettingsService::class)->set('training.self_requests_enabled', false);

        $this->manage->request($this->session, $this->employee, $this->account);
    })->throws(ValidationException::class);

    it('lets the employee withdraw a pending request, closing its approval run', function () {
        $enrolment = $this->manage->request($this->session, $this->employee, $this->account);

        $this->manage->cancel($enrolment, $this->account, 'Changed my mind');

        expect($enrolment->refresh()->status)->toBe(TrainingEnrolmentStatus::Cancelled)
            ->and($enrolment->latestApprovalRequest->status)->toBe(ApprovalStatus::Cancelled);
    });

    it('does not let an approver decide on their own training', function () {
        $enrolment = $this->manage->request($this->session, $this->employee, $this->account);
        // One account per employee (users.employee_id is unique): the admin account replaces the portal one.
        $this->account->forceFill(['employee_id' => null])->save();
        $hrAccount = adminWithRoles('HR Manager');
        $hrAccount->forceFill(['employee_id' => $this->employee->id])->save();

        $this->manage->decide($enrolment, $hrAccount, true);
    })->throws(ValidationException::class);
});

describe('capacity', function () {
    it('refuses a nomination when every seat is taken', function () {
        $session = trainingSession(['capacity' => 1], by: $this->hr);
        $this->manage->nominate($session, $this->employee, $this->hr);

        expect(fn () => $this->manage->nominate($session, attendanceEmployee(), $this->hr))
            ->toThrow(ValidationException::class, __('training.validation.session_full'));
    });

    it('refuses to approve a request once the session filled up meanwhile', function () {
        $session = trainingSession(['capacity' => 1], by: $this->hr);
        $requester = attendanceEmployee();
        $account = User::factory()->employee()->create(['employee_id' => $requester->id]);
        $request = $this->manage->request($session, $requester, $account);

        $this->manage->nominate($session, $this->employee, $this->hr);

        expect(fn () => $this->manage->decide($request->refresh(), $this->hr, true))->toThrow(ValidationException::class)
            ->and($request->refresh()->status)->toBe(TrainingEnrolmentStatus::Pending)
            ->and($session->seatsTaken())->toBe(1);
    });

    it('does not count pending, rejected or cancelled places as seats', function () {
        $session = trainingSession(['capacity' => 2], by: $this->hr);
        $account = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
        $this->manage->request($session, $this->employee, $account);
        $cancelled = $this->manage->nominate($session, attendanceEmployee(), $this->hr);
        $this->manage->cancel($cancelled, $this->hr, 'Clash');

        expect($session->seatsTaken())->toBe(0)->and($session->seatsLeft())->toBe(2);
    });

    it('never lowers the capacity below the seats taken', function () {
        $session = trainingSession(['capacity' => 2], by: $this->hr);
        $this->manage->nominate($session, $this->employee, $this->hr);
        $this->manage->nominate($session, attendanceEmployee(), $this->hr);

        app(ManageTrainingSession::class)->update($session, [...$session->only(['training_course_id', 'delivery_mode', 'location']), 'start_date' => $session->start_date->toDateString(), 'end_date' => $session->end_date->toDateString(), 'capacity' => 1]);
    })->throws(ValidationException::class);
});

describe('session cancellation', function () {
    it('cancels pending and enrolled places with the session', function () {
        $enrolled = $this->manage->nominate($this->session, $this->employee, $this->hr);
        $other = attendanceEmployee();
        $pending = $this->manage->request($this->session, $other, User::factory()->employee()->create(['employee_id' => $other->id]));

        app(ManageTrainingSession::class)->cancel($this->session, $this->hr, 'Venue closed');

        expect($enrolled->refresh()->status)->toBe(TrainingEnrolmentStatus::Cancelled)
            ->and($pending->refresh()->status)->toBe(TrainingEnrolmentStatus::Cancelled)
            ->and($pending->latestApprovalRequest->status)->toBe(ApprovalStatus::Cancelled);
    });

    it('creates sessions with a yearly TRS reference', function () {
        expect($this->session->reference)->toBe('TRS-2026-00001')
            ->and(trainingSession(course: TrainingCourse::factory()->create(), by: $this->hr)->reference)->toBe('TRS-2026-00002');
    });
});
