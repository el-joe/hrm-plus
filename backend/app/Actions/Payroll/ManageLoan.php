<?php

namespace App\Actions\Payroll;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\LoanChangeType;
use App\Enums\LoanInstalmentStatus;
use App\Enums\LoanSource;
use App\Enums\LoanStatus;
use App\Enums\LoanTransactionType;
use App\Enums\LoanType;
use App\Enums\RequestStatus;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanChange;
use App\Models\LoanInstalment;
use App\Models\User;
use App\Notifications\Payroll\LoanNotification;
use App\Services\Approvals\ApprovalRecipients;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\Payroll\LoanEligibility;
use App\Services\Payroll\LoanLedger;
use App\Services\Payroll\LoanSchedule;
use App\Services\Payroll\PayrollReferences;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The life of a loan or salary advance.
 *
 * request():   portal, API or admin on behalf; limits from LoanEligibility; starts the
 *              "loan_request" approval workflow.
 * decide():    one approval step; the approver may set the approved amount, the number of
 *              instalments and the first month (checked against the limits again).
 * applyApprovalOutcome(): approval activates the loan — instalment schedule, disbursement
 *              row in the ledger; rejection closes it.
 * repay():     early repayment (part or all) recorded in the ledger, applied to the last
 *              instalments first (the term gets shorter).
 * requestChange(): skip an instalment or reschedule the remaining balance, through the
 *              "loan_change" workflow; applied by applyChangeOutcome().
 * settleOnFinalPay(): offboarding — the whole balance becomes one instalment due on the
 *              last working day, recovered by the final payroll.
 */
class ManageLoan
{
    public function __construct(
        private readonly LoanEligibility $eligibility,
        private readonly LoanSchedule $schedule,
        private readonly LoanLedger $ledger,
        private readonly ApprovalService $approvals,
        private readonly ApprovalRecipients $recipients,
        private readonly PayrollReferences $references,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{type: string, amount: string|int|float, instalments: int|string, start_month: string, reason?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function request(Employee $employee, array $data, LoanSource $source, User $user): EmployeeLoan
    {
        $type = LoanType::from($data['type']);
        $amount = bcadd((string) $data['amount'], '0', 2);
        $instalments = (int) $data['instalments'];
        $start = $this->startMonth($data['start_month']);

        if ($source !== LoanSource::Admin && ! setting('loans.employee_requests_enabled')) {
            throw ValidationException::withMessages(['type' => __('payroll.loans.validation.requests_disabled')]);
        }

        $loan = DB::transaction(function () use ($employee, $type, $amount, $instalments, $start, $data, $source, $user): EmployeeLoan {
            Employee::query()->withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->first();

            if (($reasons = $this->eligibility->reasons($employee, $type, $amount, $instalments)) !== []) {
                throw ValidationException::withMessages($reasons);
            }

            $loan = EmployeeLoan::create([
                'reference' => $this->references->next($type->value),
                'employee_id' => $employee->id,
                'type' => $type,
                'source' => $source,
                'requested_amount' => $amount,
                'requested_instalments' => $instalments,
                'requested_start' => $start->toDateString(),
                'reason' => $data['reason'] ?? null,
                'requested_by' => $user->id,
            ]);

            try {
                $this->approvals->submit($loan, EmployeeLoan::WORKFLOW, $user);
            } catch (ApprovalException $exception) {
                throw ValidationException::withMessages(['type' => $exception->getMessage()]);
            }

            return $loan;
        });

        $this->notifyApprovers($loan->latestApprovalRequest()->first(), $loan, 'awaiting_approval');
        $this->notifyEmployee($loan, 'submitted', $user);

        return $loan->refresh();
    }

    /**
     * @param  array{approved_amount?: string|int|float|null, instalment_count?: int|string|null, start_month?: ?string}  $terms
     *
     * @throws ValidationException
     */
    public function decide(EmployeeLoan $loan, User $approver, bool $approve, ?string $reason = null, array $terms = []): void
    {
        $approval = $loan->latestApprovalRequest;

        if (! $loan->isPending() || $approval === null || ! $approval->isPending()) {
            throw ValidationException::withMessages(['reason' => __('payroll.validation.not_pending')]);
        }

        if ($approver->employee_id !== null && $approver->employee_id === $loan->employee_id) {
            throw ValidationException::withMessages(['reason' => __('payroll.loans.validation.own_loan')]);
        }

        DB::transaction(function () use ($loan, $approval, $approver, $approve, $reason, $terms): void {
            if ($approve) {
                $this->setTerms($loan, $terms);
            }

            try {
                $approve ? $this->approvals->approve($approval, $approver, $reason) : $this->approvals->reject($approval, $approver, (string) $reason);
            } catch (ApprovalException $exception) {
                throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
            }
        });

        $approval->refresh();

        if ($approval->isPending()) {
            $this->notifyApprovers($approval, $loan, 'awaiting_approval');
        }
    }

    /**
     * Listener of the loan_request workflow outcome.
     */
    public function applyApprovalOutcome(EmployeeLoan $loan, bool $approved, ?User $decidedBy, ?string $reason): void
    {
        if (! $loan->isPending()) {
            return;
        }

        if (! $approved) {
            $loan->forceFill(['status' => LoanStatus::Rejected, 'decided_at' => now(), 'decided_by' => $decidedBy?->id, 'rejection_reason' => $reason])->save();
            $this->notifyEmployee($loan, 'rejected', $decidedBy, $reason);

            return;
        }

        DB::transaction(function () use ($loan, $decidedBy): void {
            $amount = (string) ($loan->approved_amount ?? $loan->requested_amount);
            $count = $loan->instalment_count ?? $loan->requested_instalments;
            $start = $loan->start_date ?? $loan->requested_start;

            $loan->forceFill([
                'status' => LoanStatus::Active, 'approved_amount' => $amount, 'instalment_count' => $count, 'start_date' => $start->toDateString(),
                'decided_at' => now(), 'decided_by' => $decidedBy?->id, 'disbursed_at' => now(),
            ])->save();

            foreach ($this->schedule->build($amount, $count, $start) as $row) {
                $loan->instalments()->create([...$row, 'employee_id' => $loan->employee_id]);
            }

            $this->ledger->record($loan, LoanTransactionType::Disbursement, $amount, now()->toDateString(), ['reference' => 'disbursement:'.$loan->id], $decidedBy);
        });

        $this->notifyEmployee($loan->refresh(), 'approved', $decidedBy);
    }

    /**
     * Withdraw a pending request (employee or admin).
     *
     * @throws ValidationException
     */
    public function cancel(EmployeeLoan $loan, User $user, ?string $reason = null): void
    {
        if (! $loan->isPending()) {
            throw ValidationException::withMessages(['reason' => __('payroll.loans.validation.not_cancellable')]);
        }

        DB::transaction(function () use ($loan, $user, $reason): void {
            $this->closeApproval($loan->latestApprovalRequest, $user, $reason);
            $loan->forceFill(['status' => LoanStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $user->id])->save();
            $this->audit->log('payroll.loan_cancelled', $loan, meta: ['reason' => $reason], actor: $user);
        });
    }

    /**
     * Early repayment of part or all of the balance.
     *
     * @throws ValidationException
     */
    public function repay(EmployeeLoan $loan, string $amount, string $date, ?string $note, User $user): void
    {
        $amount = bcadd($amount, '0', 2);

        DB::transaction(function () use ($loan, $amount, $date, $note, $user): void {
            $loan = EmployeeLoan::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($loan->id);

            if (! $loan->isActive()) {
                throw ValidationException::withMessages(['amount' => __('payroll.loans.validation.not_active_loan')]);
            }

            if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, (string) $loan->outstanding_amount, 2) > 0) {
                throw ValidationException::withMessages(['amount' => __('payroll.loans.validation.repay_amount', ['max' => $loan->outstanding_amount])]);
            }

            $left = $amount;

            foreach ($loan->instalments()->reorder()->scheduled()->orderByDesc('due_date')->orderByDesc('sequence')->lockForUpdate()->get() as $instalment) {
                if (bccomp($left, '0', 2) <= 0) {
                    break;
                }

                $take = bccomp($left, $instalment->remaining(), 2) >= 0 ? $instalment->remaining() : $left;
                $paid = bcadd((string) $instalment->paid_amount, $take, 2);
                $instalment->forceFill([
                    'paid_amount' => $paid,
                    'status' => bccomp($paid, (string) $instalment->amount, 2) === 0 ? LoanInstalmentStatus::Repaid : LoanInstalmentStatus::Scheduled,
                    'settled_at' => bccomp($paid, (string) $instalment->amount, 2) === 0 ? now() : null,
                ])->save();
                $left = bcsub($left, $take, 2);
            }

            $this->ledger->record($loan, LoanTransactionType::EarlyRepayment, bcmul($amount, '-1', 2), CarbonImmutable::parse($date)->toDateString(), ['note' => $note], $user);
            $this->audit->log('payroll.loan_repaid', $loan, meta: ['date' => $date], actor: $user);
        });
    }

    /**
     * Ask to skip an instalment or reschedule the remaining balance (approval: "loan_change").
     *
     * @param  array{instalment_id?: int|string|null, new_instalment_count?: int|string|null, new_start_month?: ?string, reason: string}  $data
     *
     * @throws ValidationException
     */
    public function requestChange(EmployeeLoan $loan, LoanChangeType $type, array $data, User $user): LoanChange
    {
        if (! $loan->isActive()) {
            throw ValidationException::withMessages(['type' => __('payroll.loans.validation.not_active_loan')]);
        }

        if ($loan->changes()->where('status', RequestStatus::Pending)->exists()) {
            throw ValidationException::withMessages(['type' => __('payroll.loans.validation.change_pending')]);
        }

        $attributes = ['employee_loan_id' => $loan->id, 'type' => $type, 'reason' => $data['reason'], 'requested_by' => $user->id];

        if ($type === LoanChangeType::Skip) {
            $instalment = $loan->instalments()->scheduled()->whereKey($data['instalment_id'] ?? 0)->where('paid_amount', 0)->first();

            if ($instalment === null) {
                throw ValidationException::withMessages(['instalment_id' => __('payroll.loans.validation.skip_instalment')]);
            }

            $attributes['loan_instalment_id'] = $instalment->id;
        } else {
            $count = (int) ($data['new_instalment_count'] ?? 0);
            $limits = $this->eligibility->limits($loan->employee()->withoutGlobalScopes()->firstOrFail(), $loan->type);

            if ($count < 1 || $count > $limits['max_instalments']) {
                throw ValidationException::withMessages(['new_instalment_count' => __('payroll.loans.validation.max_instalments', ['max' => $limits['max_instalments']])]);
            }

            $attributes['new_instalment_count'] = $count;
            $attributes['new_start_date'] = $this->startMonth((string) ($data['new_start_month'] ?? ''), 'new_start_month')->toDateString();
        }

        $change = DB::transaction(function () use ($attributes, $user): LoanChange {
            $change = LoanChange::create($attributes);

            try {
                $this->approvals->submit($change, EmployeeLoan::CHANGE_WORKFLOW, $user);
            } catch (ApprovalException $exception) {
                throw ValidationException::withMessages(['type' => $exception->getMessage()]);
            }

            return $change;
        });

        $this->notifyApprovers($change->latestApprovalRequest()->first(), $loan, 'change_awaiting_approval');

        return $change;
    }

    /**
     * @throws ValidationException
     */
    public function decideChange(LoanChange $change, User $approver, bool $approve, ?string $reason = null): void
    {
        $approval = $change->latestApprovalRequest;

        if (! $change->isPending() || $approval === null || ! $approval->isPending()) {
            throw ValidationException::withMessages(['reason' => __('payroll.validation.not_pending')]);
        }

        if ($approver->employee_id !== null && $approver->employee_id === $change->loan()->withoutGlobalScopes()->value('employee_id')) {
            throw ValidationException::withMessages(['reason' => __('payroll.loans.validation.own_loan')]);
        }

        try {
            $approve ? $this->approvals->approve($approval, $approver, $reason) : $this->approvals->reject($approval, $approver, (string) $reason);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }
    }

    /**
     * Listener of the loan_change workflow outcome.
     */
    public function applyChangeOutcome(LoanChange $change, bool $approved, ?User $decidedBy, ?string $reason): void
    {
        if (! $change->isPending()) {
            return;
        }

        $loan = EmployeeLoan::query()->withoutGlobalScopes()->findOrFail($change->employee_loan_id);

        DB::transaction(function () use ($change, $approved, $decidedBy, $reason, $loan): void {
            $change->forceFill([
                'status' => $approved ? RequestStatus::Approved : RequestStatus::Rejected,
                'decided_at' => now(), 'decided_by' => $decidedBy?->id, 'rejection_reason' => $approved ? null : $reason,
            ])->save();

            if (! $approved || ! $loan->isActive()) {
                return;
            }

            if ($change->type === LoanChangeType::Skip) {
                $this->skip($loan, $change->instalment);
            } else {
                $this->rebuild($loan, $change->new_instalment_count, CarbonImmutable::parse($change->new_start_date));
            }

            $this->audit->log('payroll.loan_rescheduled', $loan, meta: ['change' => $change->id, 'type' => $change->type->value], actor: $decidedBy);
        });

        $this->notifyEmployee($loan, $approved ? 'change_approved' : 'change_rejected', $decidedBy, $reason);
    }

    /**
     * Offboarding: the whole balance becomes one instalment due on the last working day.
     *
     * @throws ValidationException
     */
    public function settleOnFinalPay(EmployeeLoan $loan, string $lastWorkingDay, User $user): void
    {
        if (! $loan->isActive()) {
            throw ValidationException::withMessages(['last_working_day' => __('payroll.loans.validation.not_active_loan')]);
        }

        DB::transaction(function () use ($loan, $lastWorkingDay, $user): void {
            $this->rebuild($loan, 1, CarbonImmutable::parse($lastWorkingDay), finalSettlement: true);
            $this->audit->log('payroll.loan_final_settlement', $loan, meta: ['due_date' => $lastWorkingDay], actor: $user);
        });
    }

    /**
     * Move a scheduled instalment to one month after the last one.
     */
    private function skip(EmployeeLoan $loan, LoanInstalment $instalment): void
    {
        if (! $instalment->isScheduled()) {
            return;
        }

        $last = $loan->instalments()->reorder()->orderByDesc('due_date')->orderByDesc('sequence')->first();
        $instalment->forceFill(['status' => LoanInstalmentStatus::Skipped])->save();

        $loan->instalments()->create([
            'employee_id' => $loan->employee_id,
            'sequence' => (int) $loan->instalments()->max('sequence') + 1,
            'due_date' => $last->due_date->toImmutable()->startOfMonth()->addMonthNoOverflow()->toDateString(),
            'amount' => $instalment->remaining(),
        ]);
    }

    /**
     * Replace the scheduled instalments by a new plan for the outstanding balance.
     * Partly paid instalments are closed at what was paid.
     */
    private function rebuild(EmployeeLoan $loan, int $count, CarbonImmutable $start, bool $finalSettlement = false): void
    {
        $loan = EmployeeLoan::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($loan->id);

        foreach ($loan->instalments()->scheduled()->get() as $instalment) {
            $partly = bccomp((string) $instalment->paid_amount, '0', 2) > 0;
            $instalment->forceFill($partly
                ? ['amount' => $instalment->paid_amount, 'status' => LoanInstalmentStatus::Deducted, 'settled_at' => now()]
                : ['status' => LoanInstalmentStatus::Cancelled])->save();
        }

        $next = (int) $loan->instalments()->max('sequence') + 1;
        $rows = $finalSettlement
            ? [['sequence' => $next, 'due_date' => $start->toDateString(), 'amount' => (string) $loan->outstanding_amount]]
            : $this->schedule->build((string) $loan->outstanding_amount, $count, $start, $next);

        foreach ($rows as $row) {
            $loan->instalments()->create([...$row, 'employee_id' => $loan->employee_id, 'is_final_settlement' => $finalSettlement]);
        }

        $loan->forceFill(['instalment_count' => $loan->instalments()->where('status', '!=', LoanInstalmentStatus::Cancelled)->count()])->save();
    }

    /**
     * @param  array{approved_amount?: string|int|float|null, instalment_count?: int|string|null, start_month?: ?string}  $terms
     *
     * @throws ValidationException
     */
    private function setTerms(EmployeeLoan $loan, array $terms): void
    {
        $amount = filled($terms['approved_amount'] ?? null) ? bcadd((string) $terms['approved_amount'], '0', 2) : (string) ($loan->approved_amount ?? $loan->requested_amount);
        $count = filled($terms['instalment_count'] ?? null) ? (int) $terms['instalment_count'] : ($loan->instalment_count ?? $loan->requested_instalments);
        $start = filled($terms['start_month'] ?? null) ? $this->startMonth((string) $terms['start_month']) : ($loan->start_date ?? $loan->requested_start)->toImmutable();

        if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, (string) $loan->requested_amount, 2) > 0) {
            throw ValidationException::withMessages(['approved_amount' => __('payroll.loans.validation.approved_amount')]);
        }

        $employee = Employee::query()->withoutGlobalScopes()->findOrFail($loan->employee_id);
        $reasons = $this->eligibility->reasons($employee, $loan->type, $amount, $count, $loan->id);
        unset($reasons['type']);

        if ($reasons !== []) {
            throw ValidationException::withMessages(array_combine(
                array_map(fn (string $key): string => $key === 'amount' ? 'approved_amount' : 'instalment_count', array_keys($reasons)),
                $reasons,
            ));
        }

        $loan->forceFill(['approved_amount' => $amount, 'instalment_count' => $count, 'start_date' => $start->toDateString()])->save();
    }

    /**
     * "2026-12" (or a date) → first day of that month, not before the current month.
     *
     * @throws ValidationException
     */
    private function startMonth(string $value, string $field = 'start_month'): CarbonImmutable
    {
        try {
            $month = CarbonImmutable::parse(strlen($value) === 7 ? $value.'-01' : $value)->startOfMonth();
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => __('payroll.loans.validation.start_month')]);
        }

        if ($month->lessThan(now()->startOfMonth())) {
            throw ValidationException::withMessages([$field => __('payroll.loans.validation.start_month')]);
        }

        return $month;
    }

    private function closeApproval(?ApprovalRequest $approval, User $user, ?string $reason): void
    {
        if ($approval === null || ! $approval->isPending()) {
            return;
        }

        if ($approval->requested_by === $user->id) {
            $this->approvals->cancel($approval, $user, $reason);

            return;
        }

        $approval->actions()->create([
            'approval_workflow_step_id' => $approval->current_step_id, 'step_sequence' => $approval->currentStep?->sequence,
            'step_name' => $approval->currentStep?->name, 'action' => ApprovalActionType::Cancelled, 'acted_by' => $user->id, 'reason' => $reason,
        ]);
        $approval->update(['status' => ApprovalStatus::Cancelled, 'current_step_id' => null, 'completed_at' => now()]);
        $this->audit->log('approval.cancelled', $approval, actor: $user);
    }

    private function notifyApprovers(?ApprovalRequest $approval, EmployeeLoan $loan, string $kind): void
    {
        if ($approval === null) {
            return;
        }

        foreach ($this->recipients->for($approval)->reject(fn (User $user): bool => $user->employee_id === $loan->employee_id) as $approver) {
            $approver->notify(new LoanNotification($loan, $kind));
        }
    }

    private function notifyEmployee(EmployeeLoan $loan, string $kind, ?User $actor, ?string $reason = null): void
    {
        foreach (User::query()->where('employee_id', $loan->employee_id)->get() as $user) {
            if ($actor === null || $user->id !== $actor->id) {
                $user->notify(new LoanNotification($loan, $kind, $reason));
            }
        }
    }
}
