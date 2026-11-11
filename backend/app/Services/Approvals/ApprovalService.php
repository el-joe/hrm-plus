<?php

namespace App\Services\Approvals;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\ApproverType;
use App\Events\ApprovalRequestApproved;
use App\Events\ApprovalRequestRejected;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\DataScopeManager;
use App\Services\Settings\SettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Generic multi-level approval engine.
 *
 *     $request = $approvals->submit($leaveRequest, 'leave_request', $user);
 *     $approvals->approve($request, $manager, 'ok');
 *     $approvals->reject($request, $hr, 'Not enough balance');
 *
 * A workflow (approval_workflows.key) has ordered steps; each step names who
 * may act on it (a user, a role or a permission). Approving the last step
 * closes the request as approved; any rejection closes it as rejected.
 * Every action is kept in approval_actions and written to the audit log.
 * Modules react to ApprovalRequestApproved / ApprovalRequestRejected events.
 */
class ApprovalService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly SettingsService $settings,
        private readonly DataScopeManager $dataScopes,
    ) {}

    /**
     * Start an approval run for a record.
     *
     * @throws ApprovalException when the workflow is missing, inactive, has no
     *                           steps, or the record already has a pending request
     */
    public function submit(Model $approvable, string $workflowKey, ?User $requester = null): ApprovalRequest
    {
        $workflow = ApprovalWorkflow::query()->where('key', $workflowKey)->where('is_active', true)->first();
        $firstStep = $workflow?->steps()->first();

        if ($workflow === null || $firstStep === null) {
            throw ApprovalException::workflowUnavailable($workflowKey);
        }

        return DB::transaction(function () use ($approvable, $workflow, $firstStep, $requester): ApprovalRequest {
            $hasPending = ApprovalRequest::query()
                ->whereMorphedTo('approvable', $approvable)
                ->pending()
                ->lockForUpdate()
                ->exists();

            if ($hasPending) {
                throw ApprovalException::alreadyPending();
            }

            $request = ApprovalRequest::create([
                'approval_workflow_id' => $workflow->id,
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id' => $approvable->getKey(),
                'requested_by' => $requester?->id,
                'status' => ApprovalStatus::Pending,
                'current_step_id' => $firstStep->id,
            ]);

            $this->recordAction($request, null, ApprovalActionType::Submitted, $requester, null);
            $this->audit->log('approval.submitted', $request, meta: ['workflow' => $workflow->key], actor: $requester);

            return $request;
        });
    }

    /**
     * Approve the current step and move to the next one, or finish the request.
     *
     * @throws ApprovalException when the request is closed or the user may not act
     */
    public function approve(ApprovalRequest $request, User $approver, ?string $comment = null): ApprovalRequest
    {
        $request = DB::transaction(function () use ($request, $approver, $comment): ApprovalRequest {
            $request = $this->lockForDecision($request, $approver);
            $step = $request->currentStep;

            $this->recordAction($request, $step, ApprovalActionType::Approved, $approver, $comment);

            $nextStep = $request->workflow->steps()->where('sequence', '>', $step->sequence)->first();

            $request->update($nextStep
                ? ['current_step_id' => $nextStep->id]
                : ['status' => ApprovalStatus::Approved, 'current_step_id' => null, 'completed_at' => now()]);

            $this->audit->log('approval.approved', $request, meta: [
                'step' => $step->sequence,
                'final' => $nextStep === null,
            ], actor: $approver);

            return $request;
        });

        if ($request->status === ApprovalStatus::Approved) {
            ApprovalRequestApproved::dispatch($request);
        }

        return $request;
    }

    /**
     * Reject at the current step. A reason is mandatory.
     *
     * @throws ApprovalException when the reason is empty, the request is closed or the user may not act
     */
    public function reject(ApprovalRequest $request, User $approver, string $reason): ApprovalRequest
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ApprovalException::reasonRequired();
        }

        $request = DB::transaction(function () use ($request, $approver, $reason): ApprovalRequest {
            $request = $this->lockForDecision($request, $approver);
            $step = $request->currentStep;

            $this->recordAction($request, $step, ApprovalActionType::Rejected, $approver, $reason);

            $request->update(['status' => ApprovalStatus::Rejected, 'current_step_id' => null, 'completed_at' => now()]);

            $this->audit->log('approval.rejected', $request, meta: ['step' => $step->sequence, 'reason' => $reason], actor: $approver);

            return $request;
        });

        ApprovalRequestRejected::dispatch($request, $reason);

        return $request;
    }

    /**
     * Withdraw a pending request. Only the requester may do this.
     *
     * @throws ApprovalException
     */
    public function cancel(ApprovalRequest $request, User $user, ?string $reason = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $user, $reason): ApprovalRequest {
            $request = ApprovalRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $request->isPending()) {
                throw ApprovalException::notPending();
            }

            if ($request->requested_by !== $user->id) {
                throw ApprovalException::notAllowed();
            }

            $this->recordAction($request, $request->currentStep, ApprovalActionType::Cancelled, $user, $reason);

            $request->update(['status' => ApprovalStatus::Cancelled, 'current_step_id' => null, 'completed_at' => now()]);

            $this->audit->log('approval.cancelled', $request, actor: $user);

            return $request;
        });
    }

    /**
     * Whether the user may approve or reject the request at its current step.
     */
    public function canAct(ApprovalRequest $request, User $user): bool
    {
        $step = $request->currentStep;

        if (! $request->isPending() || $step === null || ! $user->isAdmin() || ! $user->isActive()) {
            return false;
        }

        if ($request->requested_by === $user->id && ! $this->settings->get('approvals.allow_self_approval')) {
            return false;
        }

        $isApprover = match ($step->approver_type) {
            ApproverType::User => $step->approver_user_id === $user->id,
            ApproverType::Role => $user->roles->contains('id', $step->approver_role_id),
            ApproverType::Permission => $user->can($step->approver_permission),
        };

        return $isApprover && $this->approvableIsVisibleTo($request, $user);
    }

    /**
     * A data-scoped admin (e.g. a Branch Manager) may only decide on records
     * inside their scopes, whatever the step's approver rule says. Records of
     * models without HasDataScope are not restricted.
     */
    private function approvableIsVisibleTo(ApprovalRequest $request, User $user): bool
    {
        $approvable = $this->dataScopes->withoutScoping(fn (): ?Model => $request->approvable()->getResults());

        if ($approvable === null || ! method_exists($approvable, 'isVisibleTo')) {
            return true;
        }

        return $approvable->isVisibleTo($user);
    }

    /**
     * Pending requests whose current step the user is a named approver of.
     * Permission-based steps are matched against the user's effective permissions.
     *
     * Data scopes are NOT applied here (the approvable is polymorphic): a
     * module listing its own requests must constrain the approvable side, e.g.
     * `->whereHasMorph('approvable', [LeaveRequest::class])`, which the
     * global data scope then filters. approve()/reject() always re-check scope.
     *
     * @return Builder<ApprovalRequest>
     */
    public function pendingFor(User $user): Builder
    {
        $roleIds = $user->roles->pluck('id');
        $permissions = $user->getAllPermissions()->pluck('name');

        return ApprovalRequest::query()
            ->pending()
            ->when(
                ! $this->settings->get('approvals.allow_self_approval'),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->whereNull('requested_by')
                    ->orWhere('requested_by', '!=', $user->id)),
            )
            ->whereHas('currentStep', fn (Builder $step) => $step->where(fn (Builder $step) => $step
                ->where(fn (Builder $rule) => $rule
                    ->where('approver_type', ApproverType::User)
                    ->where('approver_user_id', $user->id))
                ->orWhere(fn (Builder $rule) => $rule
                    ->where('approver_type', ApproverType::Role)
                    ->whereIn('approver_role_id', $roleIds))
                ->orWhere(fn (Builder $rule) => $rule
                    ->where('approver_type', ApproverType::Permission)
                    ->whereIn('approver_permission', $permissions))));
    }

    /**
     * Re-read the request under a row lock and check the user may decide it.
     * Must run inside a transaction.
     */
    private function lockForDecision(ApprovalRequest $request, User $approver): ApprovalRequest
    {
        $request = ApprovalRequest::query()->lockForUpdate()->findOrFail($request->id);

        if (! $request->isPending()) {
            throw ApprovalException::notPending();
        }

        if (! $this->canAct($request, $approver)) {
            throw ApprovalException::notAllowed();
        }

        return $request;
    }

    private function recordAction(
        ApprovalRequest $request,
        ?ApprovalWorkflowStep $step,
        ApprovalActionType $action,
        ?User $actor,
        ?string $reason,
    ): void {
        $request->actions()->create([
            'approval_workflow_step_id' => $step?->id,
            'step_sequence' => $step?->sequence,
            'step_name' => $step?->name,
            'action' => $action,
            'acted_by' => $actor?->id,
            'reason' => $reason,
        ]);
    }
}
