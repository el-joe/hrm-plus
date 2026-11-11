<?php

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestRejected;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Role;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Event;
use Tests\Fixtures\ApprovableDocument;
use Tests\Fixtures\ScopedRecord;

/**
 * A two-level workflow: a Direct Manager first, then whoever holds leaves.manage (HR).
 *
 * @return array{workflow: ApprovalWorkflow, manager: User, hr: User}
 */
function twoStepLeaveWorkflow(): array
{
    $manager = adminWithRoles('Direct Manager');
    $hr = adminWithRoles('HR Manager');

    $workflow = ApprovalWorkflow::factory()->create(['key' => 'leave_request']);
    ApprovalWorkflowStep::factory()->for($workflow, 'workflow')
        ->approvedByRole(Role::findByName('Direct Manager', 'admin'))
        ->create(['sequence' => 1, 'name' => 'Direct manager']);
    ApprovalWorkflowStep::factory()->for($workflow, 'workflow')
        ->approvedByPermission('leaves.manage')
        ->create(['sequence' => 2, 'name' => 'HR']);

    return ['workflow' => $workflow, 'manager' => $manager, 'hr' => $hr];
}

function approvals(): ApprovalService
{
    return app(ApprovalService::class);
}

function document(): ApprovableDocument
{
    return ApprovableDocument::create(['title' => 'Annual leave 3 days']);
}

describe('submit', function () {
    it('opens a pending request at the first step and records the submission', function () {
        ['workflow' => $workflow] = twoStepLeaveWorkflow();
        $employee = User::factory()->employee()->create();
        $document = document();

        $request = approvals()->submit($document, 'leave_request', $employee);

        expect($request->status)->toBe(ApprovalStatus::Pending)
            ->and($request->currentStep->sequence)->toBe(1)
            ->and($request->approvable->is($document))->toBeTrue()
            ->and($document->latestApprovalRequest->is($request))->toBeTrue();

        $this->assertDatabaseHas('approval_actions', [
            'approval_request_id' => $request->id,
            'action' => ApprovalActionType::Submitted->value,
            'acted_by' => $employee->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'approval.submitted',
            'auditable_type' => 'approval_request',
            'auditable_id' => $request->id,
            'user_id' => $employee->id,
        ]);
    });

    it('refuses a workflow that is missing, inactive or has no steps', function (Closure $makeWorkflow) {
        $makeWorkflow();

        approvals()->submit(document(), 'leave_request');
    })->with([
        'missing' => [fn () => null],
        'inactive' => [fn () => ApprovalWorkflowStep::factory()->for(ApprovalWorkflow::factory()->inactive()->create(['key' => 'leave_request']), 'workflow')->create()],
        'no steps' => [fn () => ApprovalWorkflow::factory()->create(['key' => 'leave_request'])],
    ])->throws(ApprovalException::class);

    it('refuses a second request while one is still pending for the same record', function () {
        twoStepLeaveWorkflow();
        $document = document();
        approvals()->submit($document, 'leave_request');

        expect(fn () => approvals()->submit($document, 'leave_request'))
            ->toThrow(ApprovalException::class, __('approvals.already_pending'));

        expect(ApprovalRequest::query()->count())->toBe(1);
    });
});

describe('approve', function () {
    it('walks through every step in order and closes the request on the last one', function () {
        Event::fake([ApprovalRequestApproved::class]);
        ['manager' => $manager, 'hr' => $hr] = twoStepLeaveWorkflow();
        $request = approvals()->submit(document(), 'leave_request', User::factory()->employee()->create());

        $request = approvals()->approve($request, $manager, 'Fine by me');

        expect($request->status)->toBe(ApprovalStatus::Pending)
            ->and($request->fresh()->currentStep->sequence)->toBe(2);
        Event::assertNotDispatched(ApprovalRequestApproved::class);

        $request = approvals()->approve($request, $hr);

        expect($request->status)->toBe(ApprovalStatus::Approved)
            ->and($request->current_step_id)->toBeNull()
            ->and($request->completed_at)->not->toBeNull();
        Event::assertDispatchedTimes(ApprovalRequestApproved::class, 1);

        expect($request->actions()->get()->map(fn ($action) => [$action->action, $action->step_sequence, $action->acted_by, $action->reason])->all())
            ->toBe([
                [ApprovalActionType::Submitted, null, $request->requested_by, null],
                [ApprovalActionType::Approved, 1, $manager->id, 'Fine by me'],
                [ApprovalActionType::Approved, 2, $hr->id, null],
            ]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'approval.approved', 'auditable_id' => $request->id, 'user_id' => $hr->id]);
    });

    it('refuses an admin who is not the approver of the current step', function () {
        ['hr' => $hr] = twoStepLeaveWorkflow();
        $request = approvals()->submit(document(), 'leave_request');

        // HR is the approver of step 2, not step 1.
        expect(fn () => approvals()->approve($request, $hr))
            ->toThrow(ApprovalException::class, __('approvals.not_allowed'));

        expect($request->fresh()->currentStep->sequence)->toBe(1)
            ->and($request->actions()->count())->toBe(1);
    });

    it('refuses to act on a request that is already closed', function () {
        ['manager' => $manager] = twoStepLeaveWorkflow();
        $request = approvals()->submit(document(), 'leave_request');
        approvals()->reject($request, $manager, 'No cover available');

        expect(fn () => approvals()->approve($request, $manager))
            ->toThrow(ApprovalException::class, __('approvals.not_pending'));
    });

    it('matches the approver rule of the step', function (Closure $step, Closure $approver, bool $allowed) {
        $workflow = ApprovalWorkflow::factory()->create(['key' => 'loan']);
        seedRoles();
        $actor = $approver();
        $step($workflow, $actor);
        $request = approvals()->submit(document(), 'loan');

        expect(approvals()->canAct($request, $actor))->toBe($allowed);
    })->with([
        'named user' => [
            fn ($workflow, $actor) => ApprovalWorkflowStep::factory()->for($workflow, 'workflow')->approvedByUser($actor)->create(),
            fn () => User::factory()->admin()->create(),
            true,
        ],
        'another user' => [
            fn ($workflow) => ApprovalWorkflowStep::factory()->for($workflow, 'workflow')->approvedByUser(User::factory()->admin()->create())->create(),
            fn () => User::factory()->admin()->create(),
            false,
        ],
        'holder of the role' => [
            fn ($workflow) => ApprovalWorkflowStep::factory()->for($workflow, 'workflow')->approvedByRole(Role::findByName('Finance Officer', 'admin'))->create(),
            fn () => adminWithRoles('Finance Officer'),
            true,
        ],
        'admin without the role' => [
            fn ($workflow) => ApprovalWorkflowStep::factory()->for($workflow, 'workflow')->approvedByRole(Role::findByName('Finance Officer', 'admin'))->create(),
            fn () => adminWithRoles('Recruiter'),
            false,
        ],
        'holder of the permission' => [
            fn ($workflow) => ApprovalWorkflowStep::factory()->for($workflow, 'workflow')->approvedByPermission('loans.approve')->create(),
            fn () => adminWithRoles('Finance Officer'),
            true,
        ],
        'admin without the permission' => [
            fn ($workflow) => ApprovalWorkflowStep::factory()->for($workflow, 'workflow')->approvedByPermission('loans.approve')->create(),
            fn () => adminWithRoles('Attendance Officer'),
            false,
        ],
        'employee named as approver' => [
            fn ($workflow, $actor) => ApprovalWorkflowStep::factory()->for($workflow, 'workflow')->approvedByUser($actor)->create(),
            fn () => User::factory()->employee()->create(),
            false,
        ],
        'deactivated named user' => [
            fn ($workflow, $actor) => ApprovalWorkflowStep::factory()->for($workflow, 'workflow')->approvedByUser($actor)->create(),
            fn () => User::factory()->admin()->inactive()->create(),
            false,
        ],
    ]);

    it('does not let the requester approve their own request unless the setting allows it', function () {
        $hr = adminWithRoles('HR Manager');
        ApprovalWorkflowStep::factory()
            ->for(ApprovalWorkflow::factory()->create(['key' => 'leave_request']), 'workflow')
            ->approvedByPermission('leaves.approve')
            ->create();
        $request = approvals()->submit(document(), 'leave_request', $hr);

        expect(approvals()->canAct($request, $hr))->toBeFalse();

        app(SettingsService::class)->set('approvals.allow_self_approval', true);

        expect(approvals()->canAct($request, $hr))->toBeTrue();
    });

    it('does not let a data-restricted approver decide on a record outside their scopes', function () {
        $branchManager = adminWithRoles('Branch Manager');
        $branchManager->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => 1]);
        $branchManager->unsetRelation('dataScopes');
        ApprovalWorkflowStep::factory()
            ->for(ApprovalWorkflow::factory()->create(['key' => 'leave_request']), 'workflow')
            ->approvedByPermission('leaves.approve')
            ->create();

        $inside = approvals()->submit(ScopedRecord::create(['name' => 'cairo', 'branch_id' => 1]), 'leave_request');
        $outside = approvals()->submit(ScopedRecord::create(['name' => 'alex', 'branch_id' => 2]), 'leave_request');

        expect(approvals()->approve($inside, $branchManager)->status)->toBe(ApprovalStatus::Approved)
            ->and(fn () => approvals()->approve($outside, $branchManager))->toThrow(ApprovalException::class);

        expect($outside->fresh()->status)->toBe(ApprovalStatus::Pending);
    });
});

describe('reject', function () {
    it('closes the request at the current step with the reason', function () {
        Event::fake([ApprovalRequestRejected::class, ApprovalRequestApproved::class]);
        ['manager' => $manager] = twoStepLeaveWorkflow();
        $request = approvals()->submit(document(), 'leave_request');

        $request = approvals()->reject($request, $manager, '  No cover available  ');

        expect($request->status)->toBe(ApprovalStatus::Rejected)
            ->and($request->current_step_id)->toBeNull()
            ->and($request->completed_at)->not->toBeNull();
        $this->assertDatabaseHas('approval_actions', [
            'approval_request_id' => $request->id,
            'action' => 'rejected',
            'step_sequence' => 1,
            'step_name' => 'Direct manager',
            'acted_by' => $manager->id,
            'reason' => 'No cover available',
        ]);
        Event::assertDispatched(ApprovalRequestRejected::class, fn ($event) => $event->reason === 'No cover available');
        Event::assertNotDispatched(ApprovalRequestApproved::class);
    });

    it('requires a reason', function () {
        ['manager' => $manager] = twoStepLeaveWorkflow();
        $request = approvals()->submit(document(), 'leave_request');

        expect(fn () => approvals()->reject($request, $manager, '   '))
            ->toThrow(ApprovalException::class, __('approvals.reason_required'));

        expect($request->fresh()->status)->toBe(ApprovalStatus::Pending);
    });
});

describe('cancel', function () {
    it('lets the requester withdraw a pending request', function () {
        twoStepLeaveWorkflow();
        $employee = User::factory()->employee()->create();
        $request = approvals()->submit(document(), 'leave_request', $employee);

        $request = approvals()->cancel($request, $employee, 'Plans changed');

        expect($request->status)->toBe(ApprovalStatus::Cancelled);
        $this->assertDatabaseHas('approval_actions', ['approval_request_id' => $request->id, 'action' => 'cancelled', 'reason' => 'Plans changed']);
    });

    it('does not let anyone else withdraw it', function () {
        ['manager' => $manager] = twoStepLeaveWorkflow();
        $request = approvals()->submit(document(), 'leave_request', User::factory()->employee()->create());

        expect(fn () => approvals()->cancel($request, $manager))->toThrow(ApprovalException::class);

        expect($request->fresh()->status)->toBe(ApprovalStatus::Pending);
    });
});

describe('pendingFor', function () {
    it('lists only the pending requests waiting on the user at their current step', function () {
        ['manager' => $manager, 'hr' => $hr] = twoStepLeaveWorkflow();
        $atManager = approvals()->submit(document(), 'leave_request');
        $atHr = approvals()->approve(approvals()->submit(document(), 'leave_request'), $manager);
        approvals()->reject(approvals()->submit(document(), 'leave_request'), $manager, 'No');

        expect(approvals()->pendingFor($manager)->pluck('id')->all())->toBe([$atManager->id])
            ->and(approvals()->pendingFor($hr)->pluck('id')->all())->toBe([$atHr->id]);
    });
});
