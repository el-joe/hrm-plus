<?php

namespace App\Actions\HrRequests;

use App\Enums\ApprovalStatus;
use App\Enums\HrProfileChangeStatus;
use App\Enums\HrRequestMessageType;
use App\Enums\HrRequestPriority;
use App\Enums\HrRequestStatus;
use App\Exceptions\ApprovalException;
use App\Models\HrRequest;
use App\Models\HrRequestMessage;
use App\Models\User;
use App\Notifications\HrRequests\HrRequestNotification;
use App\Services\Approvals\ApprovalRecipients;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\HrRequests\HrRequestFileStore;
use App\Services\HrRequests\HrRequestHistory;
use App\Services\HrRequests\HrRequestRecipients;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handling an HR request after submission (App\Actions\HrRequests\SubmitHrRequest):
 *
 * open():            the request reaches HR (directly, or once its approval is granted): SLA due time,
 *                    default assignee, handlers told;
 * assign() / start(): take or hand over the request (open → in_progress);
 * reply():           an employee-visible reply (optionally asking the employee: awaiting_employee) or an
 *                    HR-only internal note — notes never reach the employee in any channel;
 * employeeReply():   the employee answers (awaiting_employee → in_progress), the handler is told;
 * resolve() / reject() / cancel(): close the request (employee told);
 * decideApproval() / applyApprovalOutcome(): the optional "hr_request" approval workflow.
 *
 * Every transition is written to hr_request_events and the audit log.
 */
class ManageHrRequest
{
    public function __construct(
        private readonly HrRequestHistory $history,
        private readonly HrRequestRecipients $recipients,
        private readonly HrRequestFileStore $files,
        private readonly ApprovalService $approvals,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * The request reaches HR: due time from the category SLA, default assignee, handlers notified.
     */
    public function open(HrRequest $request, ?User $actor): HrRequest
    {
        $request->loadMissing('category');
        $from = $request->status;

        $request->forceFill([
            'status' => HrRequestStatus::Open,
            'opened_at' => now(),
            'due_at' => now()->addHours($request->category->slaHours()),
            'assigned_to' => $request->assigned_to ?? $this->activeAssignee($request->category->default_assignee_id, $request),
        ])->save();

        if ($from !== HrRequestStatus::Open) {
            $this->history->record($request, 'status_changed', $actor, $from->value, HrRequestStatus::Open->value);
        }

        if ($request->assigned_to !== null) {
            $this->history->record($request, 'assigned', $actor, null, $request->assignee?->name);
        }

        $kind = $request->assigned_to !== null ? 'assigned' : 'submitted';

        foreach ($this->recipients->handlers($request)->reject(fn (User $user): bool => $user->id === $actor?->id) as $handler) {
            $handler->notify(new HrRequestNotification($request, $kind));
        }

        return $request;
    }

    /**
     * Assign (or unassign with null) the request.
     *
     * @throws ValidationException
     */
    public function assign(HrRequest $request, ?User $assignee, User $actor): HrRequest
    {
        $this->ensureNotClosed($request);

        if ($assignee !== null && (! $assignee->isAdmin() || ! $assignee->isActive() || ! $assignee->can('hr_requests.update')
            || $assignee->employee_id === $request->employee_id || ! $request->isVisibleTo($assignee))) {
            throw ValidationException::withMessages(['assigned_to' => __('hr_requests.validation.assignee')]);
        }

        if ($request->assigned_to === $assignee?->id) {
            return $request;
        }

        $before = $request->assignee?->name;
        $request->forceFill(['assigned_to' => $assignee?->id])->save();
        $this->history->record($request, 'assigned', $actor, $before, $assignee?->name);
        $this->audit->log('hr_request.assigned', $request, ['assigned_to' => $before], ['assigned_to' => $assignee?->name], actor: $actor);

        if ($assignee !== null && $assignee->id !== $actor->id) {
            $assignee->notify(new HrRequestNotification($request->refresh(), 'assigned'));
        }

        return $request;
    }

    /**
     * open → in_progress (the actor takes it when nobody has).
     *
     * @throws ValidationException
     */
    public function start(HrRequest $request, User $actor): HrRequest
    {
        if ($request->status !== HrRequestStatus::Open) {
            throw ValidationException::withMessages(['status' => __('hr_requests.validation.not_open')]);
        }

        if ($request->assigned_to === null) {
            $this->assign($request, $actor, $actor);
        }

        $this->transition($request, HrRequestStatus::InProgress, $actor);

        return $request;
    }

    /**
     * @param  list<UploadedFile>  $files
     *
     * @throws ValidationException
     */
    public function reply(HrRequest $request, User $actor, string $body, array $files = [], bool $internal = false, bool $awaitEmployee = false): HrRequestMessage
    {
        $this->ensureNotClosed($request);

        if (! $internal && ! $request->status->isHandled()) {
            throw ValidationException::withMessages(['body' => __('hr_requests.validation.not_handled')]);
        }

        $message = DB::transaction(function () use ($request, $actor, $body, $files, $internal, $awaitEmployee): HrRequestMessage {
            $message = $request->messages()->create([
                'type' => $internal ? HrRequestMessageType::InternalNote : HrRequestMessageType::Reply,
                'body' => $body,
                'author_id' => $actor->id,
                'from_employee' => false,
            ]);
            $this->storeFiles($request, $files, $actor, $message, internal: $internal);

            if (! $internal) {
                if ($request->first_response_at === null) {
                    $request->forceFill(['first_response_at' => now()])->save();
                }

                if ($awaitEmployee) {
                    $this->transition($request, HrRequestStatus::AwaitingEmployee, $actor);
                } elseif ($request->status === HrRequestStatus::Open) {
                    $this->transition($request, HrRequestStatus::InProgress, $actor);
                }
            }

            $this->audit->log($internal ? 'hr_request.note_added' : 'hr_request.replied', $request, meta: ['files' => count($files)], actor: $actor);

            return $message;
        });

        if (! $internal) {
            foreach ($this->recipients->employeeUsers($request) as $user) {
                $user->notify(new HrRequestNotification($request, 'reply', excerpt: $body, authorName: $actor->name));
            }
        }

        return $message;
    }

    /**
     * The employee adds to the thread (portal / app).
     *
     * @param  list<UploadedFile>  $files
     *
     * @throws ValidationException
     */
    public function employeeReply(HrRequest $request, User $user, string $body, array $files = []): HrRequestMessage
    {
        $this->ensureNotClosed($request);

        $message = DB::transaction(function () use ($request, $user, $body, $files): HrRequestMessage {
            $message = $request->messages()->create([
                'type' => HrRequestMessageType::Reply, 'body' => $body, 'author_id' => $user->id, 'from_employee' => true,
            ]);
            $this->storeFiles($request, $files, $user, $message);

            if ($request->status === HrRequestStatus::AwaitingEmployee) {
                $this->transition($request, HrRequestStatus::InProgress, $user);
            }

            $this->audit->log('hr_request.employee_replied', $request, meta: ['files' => count($files)], actor: $user);

            return $message;
        });

        if ($request->status->isHandled()) {
            foreach ($this->recipients->handlers($request) as $handler) {
                $handler->notify(new HrRequestNotification($request, 'employee_reply', excerpt: $body));
            }
        }

        return $message;
    }

    /**
     * @param  list<UploadedFile>  $files
     *
     * @throws ValidationException
     */
    public function addAttachments(HrRequest $request, User $user, array $files, bool $internal = false): void
    {
        $this->ensureNotClosed($request);

        DB::transaction(function () use ($request, $user, $files, $internal): void {
            $this->storeFiles($request, $files, $user, null, $internal);
            $this->history->record($request, 'attachment_added', $user, null, (string) count($files));
        });
    }

    /**
     * @throws ValidationException
     */
    public function changePriority(HrRequest $request, HrRequestPriority $priority, User $actor): HrRequest
    {
        $this->ensureNotClosed($request);

        if ($request->priority !== $priority) {
            $this->history->record($request, 'priority_changed', $actor, $request->priority->value, $priority->value);
            $request->forceFill(['priority' => $priority])->save();
        }

        return $request;
    }

    /**
     * @throws ValidationException
     */
    public function resolve(HrRequest $request, User $actor, ?string $note = null): HrRequest
    {
        if (! $request->status->isHandled()) {
            throw ValidationException::withMessages(['resolution_note' => __('hr_requests.validation.not_handled')]);
        }

        if ($request->profileChanges()->where('status', HrProfileChangeStatus::Pending)->exists()) {
            throw ValidationException::withMessages(['resolution_note' => __('hr_requests.validation.changes_pending')]);
        }

        DB::transaction(function () use ($request, $actor, $note): void {
            if (filled($note)) {
                $request->messages()->create(['type' => HrRequestMessageType::Reply, 'body' => $note, 'author_id' => $actor->id]);
            }

            $request->forceFill([
                'resolution_note' => $note, 'resolved_at' => now(), 'closed_at' => now(), 'closed_by' => $actor->id,
                'first_response_at' => $request->first_response_at ?? now(),
            ])->save();
            $this->transition($request, HrRequestStatus::Resolved, $actor, $note);
            $this->audit->log('hr_request.resolved', $request, actor: $actor);
        });

        $this->notifyEmployeeOfStatus($request, $note);

        return $request;
    }

    /**
     * @throws ValidationException
     */
    public function reject(HrRequest $request, User $actor, string $reason): HrRequest
    {
        if (! $request->status->isHandled()) {
            throw ValidationException::withMessages(['rejection_reason' => __('hr_requests.validation.not_handled')]);
        }

        DB::transaction(function () use ($request, $actor, $reason): void {
            $request->profileChanges()->where('status', HrProfileChangeStatus::Pending)
                ->update(['status' => HrProfileChangeStatus::Rejected->value, 'decided_at' => now(), 'decided_by' => $actor->id]);
            $request->forceFill(['rejection_reason' => $reason, 'closed_at' => now(), 'closed_by' => $actor->id])->save();
            $this->transition($request, HrRequestStatus::Rejected, $actor, $reason);
            $this->audit->log('hr_request.rejected', $request, meta: ['reason' => $reason], actor: $actor);
        });

        $this->notifyEmployeeOfStatus($request, $reason);

        return $request;
    }

    /**
     * Withdraw the request: the employee while nobody started on it, HR (hr_requests.delete) while open.
     *
     * @throws ValidationException
     */
    public function cancel(HrRequest $request, User $actor, ?string $reason = null): HrRequest
    {
        $isOwn = $actor->employee_id !== null && $actor->employee_id === $request->employee_id;

        if ($request->isClosed() || ($isOwn && ! $request->status->isCancellableByEmployee())) {
            throw ValidationException::withMessages(['status' => __('hr_requests.validation.not_cancellable')]);
        }

        DB::transaction(function () use ($request, $actor, $reason): void {
            $approval = $request->latestApprovalRequest()->first();

            if ($approval !== null && $approval->isPending()) {
                $approval->update(['status' => ApprovalStatus::Cancelled, 'current_step_id' => null, 'completed_at' => now()]);
            }

            $request->profileChanges()->where('status', HrProfileChangeStatus::Pending)
                ->update(['status' => HrProfileChangeStatus::Rejected->value, 'decided_at' => now(), 'decided_by' => $actor->id]);
            $request->forceFill(['closed_at' => now(), 'closed_by' => $actor->id])->save();
            $this->transition($request, HrRequestStatus::Cancelled, $actor, $reason);
            $this->audit->log('hr_request.cancelled', $request, meta: ['reason' => $reason], actor: $actor);
        });

        if ($actor->employee_id !== $request->employee_id) {
            $this->notifyEmployeeOfStatus($request, $reason);
        }

        return $request;
    }

    /**
     * Approve or reject at the current approval step (the engine dispatches the outcome).
     *
     * @throws ValidationException
     */
    public function decideApproval(HrRequest $request, User $approver, bool $approve, ?string $reason = null): void
    {
        $approval = $request->latestApprovalRequest()->first();

        if ($approval === null || ! $approval->isPending() || $request->status !== HrRequestStatus::PendingApproval) {
            throw ValidationException::withMessages(['reason' => __('hr_requests.validation.not_pending_approval')]);
        }

        if ($approver->employee_id !== null && $approver->employee_id === $request->employee_id) {
            throw ValidationException::withMessages(['reason' => __('hr_requests.validation.own_request')]);
        }

        if (! $approve && blank($reason)) {
            throw ValidationException::withMessages(['reason' => __('hr_requests.validation.reason_required')]);
        }

        try {
            $approve ? $this->approvals->approve($approval, $approver, $reason) : $this->approvals->reject($approval, $approver, (string) $reason);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }
    }

    /**
     * Called by App\Listeners\SyncHrRequestApprovals when the "hr_request" workflow finishes.
     */
    public function applyApprovalOutcome(HrRequest $request, bool $approved, ?User $decidedBy, ?string $reason): void
    {
        if ($request->status !== HrRequestStatus::PendingApproval) {
            return;
        }

        if ($approved) {
            DB::transaction(function () use ($request, $decidedBy): void {
                $this->history->record($request, 'approval_approved', $decidedBy);
                $this->open($request, $decidedBy);
                $this->audit->log('hr_request.approved', $request, actor: $decidedBy);
            });

            return;
        }

        DB::transaction(function () use ($request, $decidedBy, $reason): void {
            $this->history->record($request, 'approval_rejected', $decidedBy, note: $reason);
            $request->forceFill(['rejection_reason' => $reason, 'closed_at' => now(), 'closed_by' => $decidedBy?->id])->save();
            $this->transition($request, HrRequestStatus::Rejected, $decidedBy, $reason);
            $this->audit->log('hr_request.approval_rejected', $request, meta: ['reason' => $reason], actor: $decidedBy);
        });

        $this->notifyEmployeeOfStatus($request, $reason);
    }

    /**
     * Change the status and record it.
     */
    public function transition(HrRequest $request, HrRequestStatus $to, ?User $actor, ?string $note = null): void
    {
        $from = $request->status;

        if ($from === $to) {
            return;
        }

        $request->forceFill(['status' => $to])->save();
        $this->history->record($request, 'status_changed', $actor, $from->value, $to->value, $note);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function storeFiles(HrRequest $request, array $files, User $user, ?HrRequestMessage $message = null, bool $internal = false): void
    {
        foreach ($files as $file) {
            $request->attachments()->create([
                ...$this->files->store($request, $file),
                'hr_request_message_id' => $message?->id,
                'is_internal' => $internal,
                'uploaded_by' => $user->id,
            ]);
        }
    }

    private function notifyEmployeeOfStatus(HrRequest $request, ?string $reason): void
    {
        foreach ($this->recipients->employeeUsers($request) as $user) {
            $user->notify(new HrRequestNotification($request, 'status', reason: $reason));
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotClosed(HrRequest $request): void
    {
        if ($request->isClosed()) {
            throw ValidationException::withMessages(['status' => __('hr_requests.validation.closed')]);
        }
    }

    private function activeAssignee(?int $userId, HrRequest $request): ?int
    {
        if ($userId === null) {
            return null;
        }

        $user = User::query()->admins()->find($userId);

        return $user !== null && $user->isActive() && $user->employee_id !== $request->employee_id ? $user->id : null;
    }

    /**
     * @return Collection<int, User>
     */
    public function approvers(HrRequest $request): Collection
    {
        $approval = $request->latestApprovalRequest()->first();

        return $approval === null ? collect() : app(ApprovalRecipients::class)->for($approval)
            ->reject(fn (User $user): bool => $user->employee_id === $request->employee_id)->values();
    }
}
