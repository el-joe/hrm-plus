<?php

namespace App\Actions\Leave;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\LeaveDayPart;
use App\Enums\LeaveRequestSource;
use App\Enums\LeaveRequestStatus;
use App\Enums\LeaveTransactionType;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDay;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\Leave\LeaveRequestNotification;
use App\Services\Approvals\ApprovalRecipients;
use App\Services\Approvals\ApprovalService;
use App\Services\Attendance\AttendanceRecalculation;
use App\Services\Attendance\BulkAttendance;
use App\Services\Audit\AuditLogger;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Leave\Data\LeaveCount;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeaveDayCounter;
use App\Services\Leave\LeaveEligibility;
use App\Services\Leave\LeaveLedger;
use App\Services\Leave\LeavePolicyResolver;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The life of a leave request.
 *
 * submit():  validates the request (unit, notice / past dates, eligibility,
 *            working-day count, maximum per request, overlap, attachments,
 *            balance incl. pending requests — the "reservation"), stores it
 *            with one row per day and starts the leave type's approval
 *            workflow. An admin holding leaves.manage may record it as
 *            approved at once (never for their own leave).
 * decide():  one approval step (approval engine); the final outcome is
 *            applied by applyApprovalOutcome() from the SyncLeaveApprovals listener.
 * cancel():  pending, or approved and not started yet: cancelled at once;
 *            approved and started: a cancellation request through the
 *            "leave_cancellation" workflow. Admins with leaves.manage cancel directly.
 *
 * Approval books the usage in the balance ledger and recalculates the
 * attendance days already past; cancellation reverses both.
 */
class ManageLeaveRequest
{
    public function __construct(
        private readonly LeaveDayCounter $counter,
        private readonly LeaveEligibility $eligibility,
        private readonly LeaveBalanceCalculator $balances,
        private readonly LeavePolicyResolver $policies,
        private readonly LeaveLedger $ledger,
        private readonly ApprovalService $approvals,
        private readonly ApprovalRecipients $recipients,
        private readonly AttendanceRecalculation $recalculation,
        private readonly EmployeeFileStore $files,
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Count the days of a prospective request (form preview, API preview) after the request-shape checks.
     *
     * @param  array{leave_type_id: int|string, start_date: string, end_date?: ?string, day_part?: ?string, hours?: int|float|string|null}  $data
     * @return array{type: LeaveType, part: LeaveDayPart, start: string, end: string, hours: ?string, count: LeaveCount}
     */
    public function preview(Employee $employee, array $data): array
    {
        $type = LeaveType::query()->find($data['leave_type_id']);

        if ($type === null || ! $type->is_active) {
            throw ValidationException::withMessages(['leave_type_id' => __('leave.validation.type_inactive')]);
        }

        $part = LeaveDayPart::tryFrom((string) ($data['day_part'] ?? 'full')) ?? LeaveDayPart::Full;

        if (! $type->unit->allows($part) || ($part->isHalf() && ! $this->settings->get('leave.allow_half_day'))) {
            throw ValidationException::withMessages(['day_part' => __('leave.validation.unit_not_allowed', ['unit' => $type->unit->label()])]);
        }

        $start = CarbonImmutable::parse($data['start_date'])->format('Y-m-d');
        $end = $part->isPartial() ? $start : CarbonImmutable::parse($data['end_date'] ?? $start)->format('Y-m-d');

        if ($end < $start) {
            throw ValidationException::withMessages(['end_date' => __('leave.validation.end_before_start')]);
        }

        if (CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) > 366) {
            throw ValidationException::withMessages(['end_date' => __('leave.validation.too_long')]);
        }

        $hours = null;

        if ($part === LeaveDayPart::Hours) {
            $hours = bcadd((string) ($data['hours'] ?? '0'), '0', 2);

            if (bccomp($hours, '0', 2) <= 0 || bccomp($hours, '24', 2) > 0) {
                throw ValidationException::withMessages(['hours' => __('leave.validation.hours_required')]);
            }
        }

        $count = $this->counter->count($employee, $type, $start, $end, $part, $hours);

        return compact('type', 'part', 'start', 'end', 'hours', 'count');
    }

    /**
     * @param  array{leave_type_id: int|string, start_date: string, end_date?: ?string, day_part?: ?string, hours?: int|float|string|null, start_time?: ?string, reason?: ?string}  $data
     * @param  list<UploadedFile>  $attachments
     *
     * @throws ValidationException
     */
    public function submit(Employee $employee, array $data, LeaveRequestSource $source, User $user, array $attachments = [], bool $recordAsApproved = false): LeaveRequest
    {
        if ($recordAsApproved && ($user->employee_id === $employee->id || ! $user->isAdmin() || ! $user->can('leaves.manage'))) {
            throw ValidationException::withMessages(['record_as_approved' => __('leave.validation.cannot_record_approved')]);
        }

        $request = DB::transaction(function () use ($employee, $data, $source, $user, $attachments, $recordAsApproved): LeaveRequest {
            // Serialise requests of one employee (balance and overlap checks).
            Employee::query()->withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->first();

            ['type' => $type, 'part' => $part, 'start' => $start, 'end' => $end, 'hours' => $hours, 'count' => $count] = $this->preview($employee, $data);

            $this->checkDates($type, $start, $source);
            $this->checkEligibility($employee, $type, $start);
            $this->checkCount($type, $count);
            $this->checkOverlap($employee, $count);
            $this->checkBalance($employee, $type, $count, $start);

            if ($type->needsAttachment($count->total) && $attachments === []) {
                throw ValidationException::withMessages(['attachments' => __('leave.validation.attachment_required')]);
            }

            $request = LeaveRequest::create([
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start,
                'end_date' => $end,
                'day_part' => $part,
                'hours' => $hours,
                'start_time' => $part === LeaveDayPart::Hours ? ($data['start_time'] ?? null) : null,
                'days' => $count->total,
                'reason' => $data['reason'] ?? null,
                'source' => $source,
                'requested_by' => $user->id,
            ]);

            foreach ($count->days as $day) {
                $request->requestDays()->create([...$day, 'employee_id' => $employee->id]);
            }

            $this->storeAttachments($request, $employee, $attachments, $user);

            if ($recordAsApproved) {
                $request->forceFill(['status' => LeaveRequestStatus::Approved, 'decided_at' => now(), 'decided_by' => $user->id])->save();
                $this->bookUsage($request, $user);
                $this->audit->log('leave.recorded_as_approved', $request, meta: ['days' => $count->total], actor: $user);
            } else {
                try {
                    $this->approvals->submit($request, $type->approval_workflow, $user);
                } catch (ApprovalException $exception) {
                    throw ValidationException::withMessages(['leave_type_id' => $exception->getMessage()]);
                }
            }

            return $request;
        });

        if ($request->status === LeaveRequestStatus::Approved) {
            $this->recalculate($request);
            $this->notifyEmployee($request, 'approved', $user);
        } else {
            $this->notifyApprovers($request);
            $this->notifyEmployee($request, 'submitted', $user);
        }

        return $request->refresh();
    }

    /**
     * Approve or reject the current step (request or cancellation). The final
     * outcome is applied by the SyncLeaveApprovals listener.
     *
     * @throws ValidationException
     */
    public function decide(LeaveRequest $request, User $approver, bool $approve, ?string $reason = null): void
    {
        $approval = $request->latestApprovalRequest;

        if ($approval === null || ! $approval->isPending()) {
            throw ValidationException::withMessages(['reason' => __('leave.validation.not_pending')]);
        }

        if ($approver->employee_id !== null && $approver->employee_id === $request->employee_id) {
            throw ValidationException::withMessages(['reason' => __('leave.validation.own_leave')]);
        }

        try {
            $approval = $approve
                ? $this->approvals->approve($approval, $approver, $reason)
                : $this->approvals->reject($approval, $approver, (string) $reason);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        if ($approval->isPending()) {
            $this->notifyApprovers($request->refresh());
        }
    }

    /**
     * Apply a closed approval run (listener of ApprovalRequestApproved / -Rejected).
     */
    public function applyApprovalOutcome(LeaveRequest $request, ApprovalRequest $approval, bool $approved, ?User $decidedBy, ?string $reason): void
    {
        $isCancellation = $approval->workflow->key === LeaveRequest::CANCELLATION_WORKFLOW;

        if ($isCancellation) {
            if ($request->status !== LeaveRequestStatus::CancellationPending) {
                return;
            }

            if ($approved) {
                $this->markCancelled($request, $decidedBy, $request->cancellation_reason);
                $this->notifyEmployee($request, 'cancellation_approved', $decidedBy);
            } else {
                $request->forceFill(['status' => LeaveRequestStatus::Approved])->save();
                $this->notifyEmployee($request, 'cancellation_rejected', $decidedBy, $reason);
            }

            return;
        }

        if (! $request->isPending()) {
            return;
        }

        if ($approved) {
            DB::transaction(function () use ($request, $decidedBy): void {
                $request->forceFill(['status' => LeaveRequestStatus::Approved, 'decided_at' => now(), 'decided_by' => $decidedBy?->id])->save();
                $this->bookUsage($request, $decidedBy);
            });
            $this->recalculate($request);
            $this->notifyEmployee($request, 'approved', $decidedBy);

            return;
        }

        $request->forceFill([
            'status' => LeaveRequestStatus::Rejected, 'decided_at' => now(), 'decided_by' => $decidedBy?->id, 'rejection_reason' => $reason,
        ])->save();
        $this->notifyEmployee($request, 'rejected', $decidedBy, $reason);
    }

    /**
     * Cancel (or ask to cancel) a request. $asAdmin: an admin holding leaves.manage, who cancels directly.
     *
     * @return LeaveRequestStatus the resulting status (cancelled or cancellation_pending)
     *
     * @throws ValidationException
     */
    public function cancel(LeaveRequest $request, User $user, ?string $reason = null, bool $asAdmin = false): LeaveRequestStatus
    {
        $today = BulkAttendance::today()->format('Y-m-d');

        return match (true) {
            $request->isPending() => $this->withdraw($request, $user, $reason),
            $request->status === LeaveRequestStatus::Approved && ($asAdmin || ($request->start_date->format('Y-m-d') > $today
                && $this->settings->get('leave.cancel_before_start_directly'))) => $this->cancelApproved($request, $user, $reason),
            $request->status === LeaveRequestStatus::Approved => $this->requestCancellation($request, $user, (string) $reason),
            default => throw ValidationException::withMessages(['reason' => __('leave.validation.not_cancellable')]),
        };
    }

    /**
     * Add supporting files to a request that is still pending.
     *
     * @param  list<UploadedFile>  $files
     */
    public function addAttachments(LeaveRequest $request, array $files, User $user): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['attachments' => __('leave.validation.not_pending')]);
        }

        $employee = Employee::query()->withoutGlobalScopes()->findOrFail($request->employee_id);
        $this->storeAttachments($request, $employee, $files, $user);
        $this->audit->log('leave.attachments_added', $request, meta: ['count' => count($files)], actor: $user);
    }

    private function withdraw(LeaveRequest $request, User $user, ?string $reason): LeaveRequestStatus
    {
        DB::transaction(function () use ($request, $user, $reason): void {
            $this->closeApproval($request->latestApprovalRequest, $user, $reason);
            $request->forceFill([
                'status' => LeaveRequestStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancellation_reason' => $reason,
            ])->save();
        });

        return LeaveRequestStatus::Cancelled;
    }

    private function cancelApproved(LeaveRequest $request, User $user, ?string $reason): LeaveRequestStatus
    {
        $this->markCancelled($request, $user, $reason);
        $this->notifyEmployee($request, 'cancelled', $user, $reason);

        return LeaveRequestStatus::Cancelled;
    }

    private function requestCancellation(LeaveRequest $request, User $user, string $reason): LeaveRequestStatus
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => __('leave.validation.cancellation_reason_required')]);
        }

        DB::transaction(function () use ($request, $user, $reason): void {
            $request->forceFill([
                'status' => LeaveRequestStatus::CancellationPending, 'cancellation_requested_at' => now(), 'cancellation_reason' => $reason,
            ])->save();

            try {
                $this->approvals->submit($request, LeaveRequest::CANCELLATION_WORKFLOW, $user);
            } catch (ApprovalException $exception) {
                throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
            }
        });

        $this->notifyApprovers($request->refresh());

        return LeaveRequestStatus::CancellationPending;
    }

    /**
     * Cancel taken (or pending-cancellation) leave: reverse the usage and recalculate attendance.
     */
    private function markCancelled(LeaveRequest $request, ?User $user, ?string $reason): void
    {
        $wasTaken = $request->isTaken();

        DB::transaction(function () use ($request, $user, $reason, $wasTaken): void {
            if ($request->status === LeaveRequestStatus::Approved) {
                $this->closeApproval($request->latestApprovalRequest, $user, $reason);
            }

            $request->forceFill([
                'status' => LeaveRequestStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $user?->id,
                'cancellation_reason' => $reason ?? $request->cancellation_reason,
            ])->save();

            if ($wasTaken) {
                $this->reverseUsage($request, $user);
            }
        });

        if ($wasTaken) {
            $this->recalculate($request);
        }
    }

    /**
     * Close a still-pending approval run as cancelled (the requester through
     * the engine; anyone else — e.g. the employee withdrawing a request HR
     * filed for them — recorded directly with the same audit trail).
     */
    private function closeApproval(?ApprovalRequest $approval, ?User $user, ?string $reason): void
    {
        if ($approval === null || ! $approval->isPending()) {
            return;
        }

        if ($user !== null && $approval->requested_by === $user->id) {
            $this->approvals->cancel($approval, $user, $reason);

            return;
        }

        $approval->actions()->create([
            'approval_workflow_step_id' => $approval->current_step_id, 'step_sequence' => $approval->currentStep?->sequence,
            'step_name' => $approval->currentStep?->name, 'action' => ApprovalActionType::Cancelled, 'acted_by' => $user?->id, 'reason' => $reason,
        ]);
        $approval->update(['status' => ApprovalStatus::Cancelled, 'current_step_id' => null, 'completed_at' => now()]);
        $this->audit->log('approval.cancelled', $approval, actor: $user);
    }

    private function bookUsage(LeaveRequest $request, ?User $user): void
    {
        $type = $request->leaveType;

        if (! $type->tracks_balance) {
            return;
        }

        foreach ($this->usageByYear($request) as $year => [$amount, $date]) {
            $policy = $this->policies->for($request->employee()->withoutGlobalScopes()->first(), $type, $date);
            $this->ledger->record($request->employee_id, $type, $year, LeaveTransactionType::Usage, bcmul($amount, '-1', 2), $date, [
                'leave_request_id' => $request->id, 'leave_policy_id' => $policy?->id, 'reference' => "usage:{$request->id}:{$year}",
            ], $user);
        }
    }

    private function reverseUsage(LeaveRequest $request, ?User $user): void
    {
        $rows = LeaveBalanceTransaction::query()->withoutGlobalScopes()
            ->where('leave_request_id', $request->id)->where('type', LeaveTransactionType::Usage)->get();

        foreach ($rows as $row) {
            $this->ledger->record($row->employee_id, $row->leave_type_id, $row->leave_year, LeaveTransactionType::Reversal,
                bcmul((string) $row->amount, '-1', 2), now()->toDateString(), [
                    'leave_request_id' => $request->id, 'reference' => "reversal:{$request->id}:{$row->leave_year}",
                    'reason' => $request->cancellation_reason,
                ], $user);
        }
    }

    /**
     * @return array<int, array{0: string, 1: string}> leave year => [days, first date]
     */
    private function usageByYear(LeaveRequest $request): array
    {
        $years = [];

        foreach ($request->requestDays()->where('is_counted', true)->get() as $day) {
            $years[$day->leave_year] ??= ['0.00', $day->date->format('Y-m-d')];
            $years[$day->leave_year][0] = bcadd($years[$day->leave_year][0], (string) $day->amount, 2);
        }

        return $years;
    }

    /**
     * Recalculate attendance of the leave's dates that are not in the future
     * (future days are processed by the nightly run once they are over).
     */
    private function recalculate(LeaveRequest $request): void
    {
        $today = BulkAttendance::today()->format('Y-m-d');
        $dates = $request->requestDays()->get()->map(fn (LeaveRequestDay $day): string => $day->date->format('Y-m-d'))
            ->filter(fn (string $date): bool => $date <= $today)->values()->all();

        $this->recalculation->queue($request->employee_id, $dates);
    }

    private function checkDates(LeaveType $type, string $start, LeaveRequestSource $source): void
    {
        if ($source === LeaveRequestSource::Admin) {
            return;
        }

        $today = BulkAttendance::today();

        if ($start < $today->format('Y-m-d')) {
            $limit = $today->subDays(max(0, (int) $this->settings->get('leave.max_past_days')))->format('Y-m-d');

            if (! $type->allows_past_dates || $start < $limit) {
                throw ValidationException::withMessages(['start_date' => __('leave.validation.past_not_allowed')]);
            }

            return;
        }

        if ($type->notice_days > 0 && $start < $today->addDays($type->notice_days)->format('Y-m-d')) {
            throw ValidationException::withMessages(['start_date' => __('leave.validation.notice', ['days' => $type->notice_days])]);
        }
    }

    private function checkEligibility(Employee $employee, LeaveType $type, string $start): void
    {
        $reasons = $this->eligibility->reasons($employee, $type, $start);

        if ($reasons !== []) {
            throw ValidationException::withMessages(['leave_type_id' => $reasons]);
        }
    }

    private function checkCount(LeaveType $type, LeaveCount $count): void
    {
        if ($count->isEmpty()) {
            throw ValidationException::withMessages(['end_date' => __('leave.validation.no_working_days')]);
        }

        if ($type->max_days_per_request !== null && bccomp($count->total, (string) $type->max_days_per_request, 2) > 0) {
            throw ValidationException::withMessages(['end_date' => __('leave.validation.max_per_request', ['days' => $type->max_days_per_request])]);
        }
    }

    /**
     * A counted day may not be covered twice: a full day blocks everything,
     * the same half twice is refused, and partial days may not add up to more than one day.
     */
    private function checkOverlap(Employee $employee, LeaveCount $count, ?int $ignoreRequestId = null): void
    {
        $new = collect($count->days)->where('is_counted', true)->keyBy('date');

        if ($new->isEmpty()) {
            return;
        }

        /** @var Collection<string, Collection<int, LeaveRequestDay>> $existing */
        $existing = LeaveRequestDay::query()
            ->where('employee_id', $employee->id)->where('is_counted', true)
            ->whereIn('date', $new->keys()->all())
            ->whereHas('request', fn ($query) => $query->withoutGlobalScopes()->inStatus(LeaveRequestStatus::active())
                ->when($ignoreRequestId, fn ($query, int $id) => $query->whereKeyNot($id)))
            ->get()->groupBy(fn (LeaveRequestDay $day): string => $day->date->format('Y-m-d'));

        foreach ($existing as $date => $rows) {
            $day = $new->get($date);
            $taken = $rows->reduce(fn (string $sum, LeaveRequestDay $row): string => bcadd($sum, (string) $row->amount, 2), '0.00');
            $conflict = $day['day_part'] === LeaveDayPart::Full->value
                || $rows->contains(fn (LeaveRequestDay $row): bool => $row->day_part === LeaveDayPart::Full || $row->day_part->value === $day['day_part'] && $row->day_part->isHalf())
                || bccomp(bcadd($taken, $day['amount'], 2), '1.00', 2) > 0;

            if ($conflict) {
                throw ValidationException::withMessages(['start_date' => __('leave.validation.overlap', ['date' => $date])]);
            }
        }
    }

    /**
     * Available (balance minus pending requests; for a future leave year plus
     * its projected entitlement) must cover the request in each leave year it touches, unless the policy allows a negative balance
     * (down to its limit).
     */
    private function checkBalance(Employee $employee, LeaveType $type, LeaveCount $count, string $start): void
    {
        if (! $type->tracks_balance) {
            return;
        }

        $policy = $this->policies->for($employee, $type, $start);

        foreach ($count->byYear as $year => $days) {
            $after = bcsub($this->balances->availableFor($employee, $type, $year), $days, 2);

            if (bccomp($after, '0', 2) >= 0) {
                continue;
            }

            $limit = $policy?->negative_limit_days !== null ? bcmul((string) $policy->negative_limit_days, '-1', 2) : null;

            if ($policy === null || ! $policy->allow_negative_balance || ($limit !== null && bccomp($after, $limit, 2) < 0)) {
                throw ValidationException::withMessages(['end_date' => __('leave.validation.insufficient_balance', [
                    'available' => bcadd($after, $days, 2), 'requested' => $days,
                ])]);
            }
        }
    }

    /**
     * @param  list<UploadedFile>  $attachments
     */
    private function storeAttachments(LeaveRequest $request, Employee $employee, array $attachments, User $user): void
    {
        foreach ($attachments as $file) {
            $request->attachments()->create([...$this->files->store($employee, $file, 'leave'), 'uploaded_by' => $user->id]);
        }
    }

    private function notifyApprovers(LeaveRequest $request): void
    {
        $approval = $request->latestApprovalRequest()->first();

        if ($approval === null) {
            return;
        }

        foreach ($this->recipients->for($approval)->reject(fn (User $user): bool => $user->employee_id === $request->employee_id) as $approver) {
            $approver->notify(new LeaveRequestNotification($request, 'awaiting_approval'));
        }
    }

    /**
     * The employee's own account, plus the admin who filed the request on their behalf (not the actor).
     */
    private function notifyEmployee(LeaveRequest $request, string $kind, ?User $actor, ?string $reason = null): void
    {
        $users = User::query()->where('employee_id', $request->employee_id)->get();

        if ($request->requested_by !== null) {
            $users = $users->push(User::query()->find($request->requested_by))->filter();
        }

        foreach ($users->unique('id') as $user) {
            if ($actor !== null && $user->id === $actor->id) {
                continue;
            }

            $user->notify(new LeaveRequestNotification($request, $kind, $reason));
        }
    }
}
