<?php

namespace App\Actions\Payroll;

use App\Enums\ApprovalStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayrollAdjustmentStatus;
use App\Enums\PayrollItemStatus;
use App\Enums\PayrollLineSource;
use App\Enums\PayrollRunStatus;
use App\Enums\PayrollRunType;
use App\Enums\PayslipStatus;
use App\Enums\TaskStatus;
use App\Exceptions\ApprovalException;
use App\Jobs\CalculatePayrollRun;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\LoanInstalment;
use App\Models\OffboardingCase;
use App\Models\OffboardingItem;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Notifications\Payroll\PayrollRunNotification;
use App\Notifications\Payroll\PayslipIssuedNotification;
use App\Services\Approvals\ApprovalRecipients;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\DataScopeManager;
use App\Services\Payroll\Data\PayrollLine;
use App\Services\Payroll\Data\PayrollResult;
use App\Services\Payroll\LoanInstalments;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayrollCorrections;
use App\Services\Payroll\PayrollPopulation;
use App\Services\Payroll\PayrollReferences;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Every state change of a payroll run (spec module 9 F / H). Each method
 * runs in a transaction with the run row locked (FOR UPDATE), checks the
 * state, writes and audits ("payroll.run_*"):
 *
 *   create → (updateScope / refresh / removeEmployee) → calculate (any number
 *   of times while draft / calculated) → submit (approval engine,
 *   "payroll_run") → applyApprovalOutcome → finalise (locks, issues payslips,
 *   marks loan instalments deducted, applies carried adjustments) → reverse
 *   (mirror run with negative amounts; the original is never edited beyond
 *   its status).
 *
 * Double processing: a calculation first claims the run (status
 * "calculating" under the lock), so two calculations cannot overlap;
 * items reserve the employee's pay slot of the period (unique index), so two
 * runs cannot pay the same employee twice for the same period.
 */
class ManagePayrollRun
{
    public function __construct(
        private readonly PayrollPopulation $population,
        private readonly PayrollCalculator $calculator,
        private readonly PayrollReferences $references,
        private readonly ApprovalService $approvals,
        private readonly ApprovalRecipients $recipients,
        private readonly LoanInstalments $loans,
        private readonly AuditLogger $audit,
        private readonly DataScopeManager $scopes,
    ) {}

    /**
     * @param  array<string, mixed>  $scope
     *
     * @throws ValidationException
     */
    public function create(PayrollPeriod $period, PayrollRunType $type, array $scope, User $user, ?string $name = null, ?string $notes = null, ?OffboardingCase $case = null): PayrollRun
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages(['payroll_period_id' => __('payroll_runs.validation.period_closed')]);
        }

        if ($this->population->isEmpty($scope)) {
            throw ValidationException::withMessages(['scope' => __('payroll_runs.validation.scope_required')]);
        }

        $scope = $this->population->normalise($scope);

        try {
            return DB::transaction(function () use ($period, $type, $scope, $user, $name, $notes, $case): PayrollRun {
                PayrollPeriod::query()->whereKey($period->id)->lockForUpdate()->first();
                $preview = $this->population->preview($period, $type, $scope);

                if ($preview['included'] === []) {
                    throw ValidationException::withMessages(['scope' => __('payroll_runs.validation.nobody_included')]);
                }

                $run = PayrollRun::create([
                    'reference' => $this->references->next('run'),
                    'payroll_period_id' => $period->id,
                    'type' => $type,
                    'name' => $name ?: $this->defaultName($period, $type, $scope),
                    'status' => PayrollRunStatus::Draft,
                    'scope' => $scope,
                    'scope_summary' => $this->population->describe($scope),
                    'offboarding_case_id' => $case?->id,
                    'currency' => (string) setting('localisation.currency_code'),
                    'notes' => $notes,
                    'created_by' => $user->id,
                ]);

                $this->insertItems($run, $preview['included']);
                $this->refreshSummary($run);
                $this->audit->log('payroll.run_created', $run, newValues: ['type' => $type->value, 'scope' => $scope], meta: [
                    'period' => $period->code, 'employees' => count($preview['included']), 'excluded' => count($preview['excluded']),
                ], actor: $user);

                return $run;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['scope' => __('payroll_runs.validation.double_pay')]);
        }
    }

    /**
     * Change the scope of a draft / calculated run and rebuild its population.
     *
     * @param  array<string, mixed>  $scope
     */
    public function updateScope(PayrollRun $run, array $scope, User $user): PayrollRun
    {
        if ($this->population->isEmpty($scope)) {
            throw ValidationException::withMessages(['scope' => __('payroll_runs.validation.scope_required')]);
        }

        $old = $run->scope;
        $run = $this->refresh($run, $user, $this->population->normalise($scope));
        $this->audit->log('payroll.run_scope_changed', $run, ['scope' => $old], ['scope' => $run->scope], actor: $user);

        return $run;
    }

    /**
     * Re-evaluate the scope: add new eligible employees, drop those no longer eligible.
     *
     * @param  array<string, mixed>|null  $scope
     */
    public function refresh(PayrollRun $run, User $user, ?array $scope = null): PayrollRun
    {
        try {
            return DB::transaction(function () use ($run, $user, $scope): PayrollRun {
                $run = $this->lockEditable($run);
                PayrollPeriod::query()->whereKey($run->payroll_period_id)->lockForUpdate()->first();
                $scope ??= $run->scope;
                $preview = $this->population->preview($run->period, $run->type, $scope, $run->id);
                $wanted = collect($preview['included'])->keyBy('employee_id');
                $current = $run->items()->withoutGlobalScopes()->get()->keyBy('employee_id');
                $removed = $current->diffKeys($wanted);
                $added = $wanted->diffKeys($current);

                foreach ($removed as $item) {
                    $this->deleteItem($item);
                }

                $this->insertItems($run, $added->values()->all());
                $run->forceFill(['scope' => $scope, 'scope_summary' => $this->population->describe($scope)])->save();

                if ($removed->isNotEmpty() || $added->isNotEmpty()) {
                    $run->forceFill(['status' => PayrollRunStatus::Draft])->save();
                }

                $this->refreshSummary($run);
                $this->audit->log('payroll.run_population_refreshed', $run, meta: [
                    'added' => $added->keys()->values()->all(), 'removed' => $removed->keys()->values()->all(),
                ], actor: $user);

                return $run;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['scope' => __('payroll_runs.validation.double_pay')]);
        }
    }

    /**
     * Leave one employee out of a draft / calculated run (added to the exclude list, slot released).
     */
    public function removeEmployee(PayrollRun $run, PayrollItem $item, string $reason, User $user): PayrollRun
    {
        return DB::transaction(function () use ($run, $item, $reason, $user): PayrollRun {
            $run = $this->lockEditable($run);
            abort_unless($item->payroll_run_id === $run->id, 404);

            if ($run->items()->withoutGlobalScopes()->count() <= 1) {
                throw ValidationException::withMessages(['item' => __('payroll_runs.validation.last_employee')]);
            }

            $scope = $run->scope;
            $scope['exclude_employee_ids'] = array_values(array_unique([...($scope['exclude_employee_ids'] ?? []), $item->employee_id]));
            $scope['employee_ids'] = array_values(array_diff($scope['employee_ids'] ?? [], [$item->employee_id]));
            $this->deleteItem($item);
            $run->forceFill(['scope' => $scope, 'scope_summary' => $this->population->describe($scope)])->save();
            $this->refreshSummary($run);
            $this->audit->log('payroll.run_employee_removed', $run, meta: ['employee_id' => $item->employee_id, 'reason' => $reason], actor: $user);

            return $run;
        });
    }

    /**
     * Calculate (or recalculate) every employee of the run. Above setting
     * payroll.sync_calculation_limit employees the work is queued
     * (CalculatePayrollRun) and the run shows its progress.
     */
    public function calculate(PayrollRun $run, User $user): PayrollRun
    {
        [$run, $previous] = DB::transaction(function () use ($run): array {
            $run = $this->lockEditable($run);
            $previous = $run->status;
            $count = $run->items()->withoutGlobalScopes()->count();

            if ($count === 0) {
                throw ValidationException::withMessages(['run' => __('payroll_runs.validation.nobody_included')]);
            }

            $run->forceFill([
                'status' => PayrollRunStatus::Calculating, 'progress_total' => $count, 'progress_done' => 0, 'calculation_error' => null,
            ])->save();

            return [$run, $previous];
        });

        if ($run->progress_total > (int) setting('payroll.sync_calculation_limit')) {
            CalculatePayrollRun::dispatch($run->id, $user->id, $previous->value);
            $this->audit->log('payroll.run_calculation_queued', $run, meta: ['employees' => $run->progress_total], actor: $user);

            return $run;
        }

        $this->runCalculation($run, $user, $previous);

        return $run->refresh();
    }

    /**
     * The calculation loop (sync or from the queued job). The run must be in "calculating".
     */
    public function runCalculation(PayrollRun $run, ?User $user, PayrollRunStatus $previous): void
    {
        $changed = 0;

        try {
            if ($run->type->paysSalary()) {
                // Surface late corrections of earlier finalised periods first, so this run carries them.
                app(PayrollCorrections::class)->scan($user, $run->items()->withoutGlobalScopes()->pluck('employee_id')->all());
            }

            $this->scopes->withoutScoping(function () use ($run, &$changed): void {
                $run->items()->withoutGlobalScopes()->with(['employee' => fn ($query) => $query->withoutGlobalScopes()])->orderBy('id')
                    ->chunkById(50, function (Collection $items) use ($run, &$changed): void {
                        foreach ($items as $item) {
                            $changed += $this->calculateItem($run, $item) ? 1 : 0;
                        }

                        PayrollRun::query()->whereKey($run->id)->increment('progress_done', $items->count());
                    });
            });
        } catch (Throwable $exception) {
            $run->forceFill(['status' => $previous, 'calculation_error' => mb_substr($exception->getMessage(), 0, 2000)])->save();
            $this->audit->log('payroll.run_calculation_failed', $run, meta: ['error' => mb_substr($exception->getMessage(), 0, 500)], actor: $user);

            throw $exception;
        }

        DB::transaction(function () use ($run, $user, $changed): void {
            $run = PayrollRun::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($run->id);
            $first = $run->calculated_at === null;
            $this->refreshSummary($run);
            $run->forceFill(['status' => PayrollRunStatus::Calculated, 'calculated_at' => now(), 'calculated_by' => $user?->id])->save();
            $this->audit->log($first ? 'payroll.run_calculated' : 'payroll.run_recalculated', $run, meta: [
                'employees' => $run->employee_count, 'changed' => $changed,
                'gross' => (string) $run->gross_total, 'net' => (string) $run->net_total,
                'blocking' => $run->items()->withoutGlobalScopes()->where('has_blocking_warning', true)->count(),
            ], actor: $user);
        });
    }

    /**
     * Calculate one employee and store the result (lines replaced, adjustments re-reserved).
     * Returns whether the result changed.
     */
    public function calculateItem(PayrollRun $run, PayrollItem $item): bool
    {
        /** @var Employee $employee */
        $employee = $item->employee;

        try {
            $adjustments = DB::transaction(function () use ($run, $item): Collection {
                PayrollAdjustment::query()->withoutGlobalScopes()->where('payroll_item_id', $item->id)->update(['payroll_item_id' => null]);

                return $this->adjustmentsFor($run, $item);
            });

            $leavingDate = $run->type === PayrollRunType::FinalSettlement ? $run->offboardingCase?->last_working_date?->toDateString() : null;
            $result = $this->calculator->calculate($employee, $run->period, $run->type, $adjustments, $leavingDate);
        } catch (Throwable $exception) {
            report($exception);
            $item->forceFill(['status' => PayrollItemStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 2000), 'has_blocking_warning' => true])->save();

            return true;
        }

        return DB::transaction(function () use ($item, $result): bool {
            $item = PayrollItem::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($item->id);
            $changed = $item->calculation_hash !== $result->hash;
            $this->storeResult($item, $result);

            if ($result->adjustmentIds !== []) {
                PayrollAdjustment::query()->withoutGlobalScopes()->whereIn('id', $result->adjustmentIds)->pending()->whereNull('payroll_item_id')
                    ->update(['payroll_item_id' => $item->id]);
            }

            return $changed;
        });
    }

    /**
     * Send a calculated run for approval (workflow "payroll_run").
     */
    public function submit(PayrollRun $run, User $user): PayrollRun
    {
        $run = DB::transaction(function () use ($run, $user): PayrollRun {
            $run = PayrollRun::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($run->id);

            if ($run->status !== PayrollRunStatus::Calculated) {
                throw ValidationException::withMessages(['run' => __('payroll_runs.validation.not_calculated')]);
            }

            $items = $run->items()->withoutGlobalScopes();

            if ((clone $items)->where('status', '!=', PayrollItemStatus::Calculated)->exists()) {
                throw ValidationException::withMessages(['run' => __('payroll_runs.validation.items_not_calculated')]);
            }

            if ((clone $items)->where('has_blocking_warning', true)->exists()) {
                throw ValidationException::withMessages(['run' => __('payroll_runs.validation.blocking_warnings')]);
            }

            try {
                $this->approvals->submit($run, PayrollRun::WORKFLOW, $user);
            } catch (ApprovalException $exception) {
                throw ValidationException::withMessages(['run' => $exception->getMessage()]);
            }

            $run->forceFill(['status' => PayrollRunStatus::PendingApproval, 'submitted_at' => now(), 'submitted_by' => $user->id])->save();
            $this->audit->log('payroll.run_submitted', $run, meta: ['net' => (string) $run->net_total, 'employees' => $run->employee_count], actor: $user);

            return $run;
        });

        $this->notifyApprovers($run);

        return $run;
    }

    /**
     * Approve or reject the current approval step.
     */
    public function decide(PayrollRun $run, User $user, bool $approve, ?string $reason = null): PayrollRun
    {
        $approval = $run->latestApprovalRequest;

        if ($approval === null || ! $approval->isPending()) {
            throw ValidationException::withMessages(['run' => __('payroll_runs.validation.not_pending')]);
        }

        try {
            $approval = $approve ? $this->approvals->approve($approval, $user, $reason) : $this->approvals->reject($approval, $user, (string) $reason);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        if ($approval->isPending()) {
            $this->audit->log('payroll.run_step_approved', $run, meta: ['step' => $approval->currentStep?->name, 'comment' => $reason], actor: $user);
            $this->notifyApprovers($run->refresh());
        }

        return $run->refresh();
    }

    /**
     * Final outcome of the approval workflow (SyncPayrollApprovals).
     */
    public function applyApprovalOutcome(PayrollRun $run, bool $approved, ?User $decidedBy, ?string $reason): void
    {
        $run = DB::transaction(function () use ($run, $approved, $decidedBy, $reason): PayrollRun {
            $run = PayrollRun::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($run->id);

            if ($run->status !== PayrollRunStatus::PendingApproval) {
                return $run;
            }

            $run->forceFill($approved
                ? ['status' => PayrollRunStatus::Approved, 'approved_at' => now()]
                : ['status' => PayrollRunStatus::Calculated])->save();
            $this->audit->log($approved ? 'payroll.run_approved' : 'payroll.run_rejected', $run, meta: ['reason' => $reason], actor: $decidedBy);

            return $run;
        });

        foreach (User::query()->whereIn('id', array_filter([$run->submitted_by]))->get() as $user) {
            $user->notify(new PayrollRunNotification($run, $approved ? 'approved' : 'rejected', $reason));
        }
    }

    /**
     * Lock the approved run for good: payslips issued, loan instalments marked
     * deducted, carried adjustments applied, offboarding settlement item done.
     */
    public function finalise(PayrollRun $run, User $user): PayrollRun
    {
        $run = DB::transaction(function () use ($run, $user): PayrollRun {
            $run = PayrollRun::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($run->id);

            if ($run->status !== PayrollRunStatus::Approved) {
                throw ValidationException::withMessages(['run' => __('payroll_runs.validation.not_approved')]);
            }

            $approval = ApprovalRequest::query()->whereMorphedTo('approvable', $run)->latest('id')->first();

            if ($approval?->status !== ApprovalStatus::Approved) {
                throw ValidationException::withMessages(['run' => __('payroll_runs.validation.not_approved')]);
            }

            $items = $run->items()->withoutGlobalScopes()->with(['lines', 'employee' => fn ($query) => $query->withoutGlobalScopes()])->orderBy('id')->get();
            $locale = (string) setting('localisation.default_locale');

            foreach ($items as $item) {
                foreach ($item->lines->where('source', PayrollLineSource::Loan) as $line) {
                    if (bccomp((string) $line->amount, '0', 2) > 0 && $line->sourceable_id !== null) {
                        $instalment = LoanInstalment::query()->findOrFail($line->sourceable_id);
                        $this->loans->markDeducted($instalment, (string) $line->amount, $item, $user, $run->period->pay_date);
                    }
                }

                PayrollAdjustment::query()->withoutGlobalScopes()->where('payroll_item_id', $item->id)->pending()
                    ->update(['status' => PayrollAdjustmentStatus::Applied, 'applied_at' => now()]);

                Payslip::create([
                    'number' => $this->references->next('payslip'),
                    'payroll_item_id' => $item->id,
                    'payroll_run_id' => $run->id,
                    'payroll_period_id' => $run->payroll_period_id,
                    'employee_id' => $item->employee_id,
                    'branch_id' => $item->branch_id,
                    'department_id' => $item->department_id,
                    'status' => PayslipStatus::Issued,
                    'payment_status' => bccomp((string) $item->net_pay, '0', 2) <= 0 ? PaymentStatus::Paid : PaymentStatus::Unpaid,
                    'currency' => $item->currency,
                    'gross_pay' => $item->gross_pay,
                    'total_deductions' => $item->total_deductions,
                    'net_pay' => $item->net_pay,
                    'locale' => User::query()->where('employee_id', $item->employee_id)->value('locale') ?? $locale,
                    'issued_at' => now(),
                ]);
            }

            $run->forceFill([
                'status' => PayrollRunStatus::Finalised,
                'finalised_at' => now(),
                'finalised_by' => $user->id,
                'totals_hash' => hash('sha256', $items->pluck('calculation_hash')->implode('|')),
            ])->save();

            if ($run->offboarding_case_id !== null) {
                $this->completeSettlementItem($run, $user);
            }

            $this->audit->log('payroll.run_finalised', $run, meta: [
                'employees' => $items->count(), 'gross' => (string) $run->gross_total, 'net' => (string) $run->net_total, 'totals_hash' => $run->totals_hash,
            ], actor: $user);

            return $run;
        });

        $this->notifyFinalised($run);

        return $run;
    }

    /**
     * Cancel a run that was never finalised: its pay slots and adjustments are released.
     */
    public function cancel(PayrollRun $run, User $user, string $reason): PayrollRun
    {
        return DB::transaction(function () use ($run, $user, $reason): PayrollRun {
            $run = $this->lockEditable($run);
            $itemIds = $run->items()->withoutGlobalScopes()->pluck('id');
            PayrollItem::query()->withoutGlobalScopes()->whereIn('id', $itemIds)->update(['pay_slot' => null]);
            PayrollAdjustment::query()->withoutGlobalScopes()->whereIn('payroll_item_id', $itemIds)->pending()->update(['payroll_item_id' => null]);
            $run->forceFill(['status' => PayrollRunStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'notes' => trim(($run->notes ? $run->notes."\n" : '').$reason)])->save();
            $this->audit->log('payroll.run_cancelled', $run, meta: ['reason' => $reason], actor: $user);

            return $run;
        });
    }

    /**
     * Reverse a finalised run (nothing paid yet): a "reversal" run mirrors every item with
     * negative amounts, payslips are voided, loan deductions reversed, applied adjustments
     * become pending again and the pay slots are released so the period can be paid again.
     */
    public function reverse(PayrollRun $run, User $user, string $reason): PayrollRun
    {
        return DB::transaction(function () use ($run, $user, $reason): PayrollRun {
            $run = PayrollRun::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($run->id);

            if ($run->status !== PayrollRunStatus::Finalised || $run->type === PayrollRunType::Reversal) {
                throw ValidationException::withMessages(['run' => __('payroll_runs.validation.not_finalised')]);
            }

            if (Payslip::query()->withoutGlobalScopes()->where('payroll_run_id', $run->id)->where('amount_paid', '>', 0)->exists()) {
                throw ValidationException::withMessages(['run' => __('payroll_runs.validation.payments_recorded')]);
            }

            $reversal = PayrollRun::create([
                'reference' => $this->references->next('run'),
                'payroll_period_id' => $run->payroll_period_id,
                'type' => PayrollRunType::Reversal,
                'name' => __('payroll_runs.runs.reversal_name', ['reference' => $run->reference]),
                'status' => PayrollRunStatus::Finalised,
                'scope' => $run->scope,
                'scope_summary' => $run->scope_summary,
                'branch_id' => $run->branch_id,
                'corrects_run_id' => $run->id,
                'currency' => $run->currency,
                'notes' => $reason,
                'created_by' => $user->id,
            ]);

            $items = $run->items()->withoutGlobalScopes()->with('lines')->get();

            foreach ($items as $item) {
                $mirror = $item->replicate(['pay_slot', 'payroll_run_id']);
                $mirror->forceFill([
                    'payroll_run_id' => $reversal->id, 'pay_slot' => null,
                    'gross_pay' => bcmul((string) $item->gross_pay, '-1', 2), 'total_deductions' => bcmul((string) $item->total_deductions, '-1', 2),
                    'employer_contributions' => bcmul((string) $item->employer_contributions, '-1', 2), 'net_pay' => bcmul((string) $item->net_pay, '-1', 2),
                    'taxable_base' => bcmul((string) $item->taxable_base, '-1', 2), 'insurable_base' => bcmul((string) $item->insurable_base, '-1', 2),
                    'warnings' => [], 'has_blocking_warning' => false, 'calculated_at' => now(),
                ])->save();

                foreach ($item->lines as $line) {
                    $copy = $line->replicate(['payroll_item_id', 'payroll_run_id']);
                    $copy->forceFill([
                        'payroll_item_id' => $mirror->id, 'payroll_run_id' => $reversal->id, 'source' => PayrollLineSource::Reversal,
                        'amount' => bcmul((string) $line->amount, '-1', 2),
                        'explanation' => [['key' => 'reversal', 'params' => ['reference' => $run->reference]]],
                    ])->save();

                    if ($line->source === PayrollLineSource::Loan && $line->sourceable_id !== null && bccomp((string) $line->amount, '0', 2) > 0) {
                        $instalment = LoanInstalment::query()->findOrFail($line->sourceable_id);

                        if (bccomp((string) $instalment->paid_amount, '0', 2) > 0) {
                            $this->loans->reverseDeduction($instalment, $mirror, $user);
                        }
                    }
                }

                $mirror->forceFill(['calculation_hash' => hash('sha256', 'reversal|'.$item->calculation_hash)])->save();
                PayrollAdjustment::query()->withoutGlobalScopes()->where('payroll_item_id', $item->id)
                    ->update(['status' => PayrollAdjustmentStatus::Pending, 'applied_at' => null, 'payroll_item_id' => null]);
            }

            PayrollItem::query()->withoutGlobalScopes()->where('payroll_run_id', $run->id)->update(['pay_slot' => null]);
            Payslip::query()->withoutGlobalScopes()->where('payroll_run_id', $run->id)->update(['status' => PayslipStatus::Void, 'voided_at' => now()]);
            $this->refreshSummary($reversal);
            $reversal->forceFill(['finalised_at' => now(), 'finalised_by' => $user->id, 'totals_hash' => hash('sha256', 'reversal|'.$run->totals_hash)])->save();
            $run->forceFill(['status' => PayrollRunStatus::Reversed, 'reversed_at' => now(), 'reversed_by' => $user->id, 'reversal_reason' => $reason])->save();

            $this->audit->log('payroll.run_reversed', $run, meta: ['reason' => $reason, 'reversal' => $reversal->reference, 'employees' => $items->count()], actor: $user);
            $this->audit->log('payroll.run_reversal_created', $reversal, meta: ['reverses' => $run->reference], actor: $user);

            return $reversal;
        });
    }

    /**
     * Pending adjustments this item carries: corrections of earlier periods (or manual ones)
     * not reserved by another live run.
     *
     * @return Collection<int, PayrollAdjustment>
     */
    private function adjustmentsFor(PayrollRun $run, PayrollItem $item): Collection
    {
        return PayrollAdjustment::query()->withoutGlobalScopes()->with('originalPeriod')
            ->where('employee_id', $item->employee_id)->pending()->whereNull('payroll_item_id')
            ->where(fn ($query) => $query->whereNull('original_period_id')
                ->orWhereHas('originalPeriod', fn ($period) => $period->where('end_date', '<', $run->period->start_date->toDateString())))
            ->lockForUpdate()->orderBy('id')->get();
    }

    private function storeResult(PayrollItem $item, PayrollResult $result): void
    {
        $item->lines()->delete();

        $rows = array_map(fn (PayrollLine $line): array => [
            ...$line->toArray(),
            'explanation' => json_encode($line->explanation, JSON_UNESCAPED_UNICODE),
            'payroll_item_id' => $item->id, 'payroll_run_id' => $item->payroll_run_id, 'employee_id' => $item->employee_id,
            'created_at' => now(), 'updated_at' => now(),
        ], $result->lines);

        foreach (array_chunk($rows, 200) as $chunk) {
            PayrollItemLine::query()->insert($chunk);
        }

        $item->forceFill([
            'status' => PayrollItemStatus::Calculated,
            'pay_basis' => $result->payBasis,
            'basic_salary' => $result->basicSalary,
            'proration_factor' => $result->prorationFactor,
            'gross_pay' => $result->grossPay,
            'total_deductions' => $result->totalDeductions,
            'employer_contributions' => $result->employerContributions,
            'taxable_base' => $result->taxableBase,
            'insurable_base' => $result->insurableBase,
            'net_pay' => $result->netPay,
            'inputs' => $result->inputs,
            'quantities' => $result->quantities,
            'rule_versions' => $result->ruleVersions,
            'warnings' => $result->warnings,
            'has_blocking_warning' => $result->hasBlockingWarning(),
            'calculation_hash' => $result->hash,
            'error' => null,
            'calculated_at' => now(),
        ])->save();
    }

    /**
     * @param  list<array<string, mixed>>  $rows  included rows of PayrollPopulation::preview()
     */
    private function insertItems(PayrollRun $run, array $rows): void
    {
        foreach ($rows as $row) {
            PayrollItem::create([
                'payroll_run_id' => $run->id,
                'payroll_period_id' => $run->payroll_period_id,
                'employee_id' => $row['employee_id'],
                'pay_slot' => $run->type->holdsPaySlot() ? PayrollItem::PAY_SLOT : null,
                'status' => PayrollItemStatus::Pending,
                'branch_id' => $row['branch_id'],
                'department_id' => $row['department_id'],
                'team_id' => $row['team_id'],
                'position_id' => $row['position_id'],
                'cost_center_id' => $row['cost_center_id'],
                'moved_in_period' => $row['moved'],
                'currency' => $run->currency,
            ]);
        }
    }

    private function deleteItem(PayrollItem $item): void
    {
        PayrollAdjustment::query()->withoutGlobalScopes()->where('payroll_item_id', $item->id)->pending()->update(['payroll_item_id' => null]);
        $item->lines()->delete();
        PayrollItem::query()->withoutGlobalScopes()->whereKey($item->id)->delete();
    }

    /**
     * Totals, head count and the single branch of the population (null when several).
     */
    private function refreshSummary(PayrollRun $run): void
    {
        $totals = PayrollItem::query()->withoutGlobalScopes()->where('payroll_run_id', $run->id)
            ->selectRaw('count(*) as employees, coalesce(sum(gross_pay), 0) as gross, coalesce(sum(total_deductions), 0) as deductions, coalesce(sum(net_pay), 0) as net, coalesce(sum(employer_contributions), 0) as employer, count(distinct branch_id) as branches, min(branch_id) as branch')
            ->first();

        $run->forceFill([
            'employee_count' => (int) $totals->employees,
            'gross_total' => (string) $totals->gross,
            'deductions_total' => (string) $totals->deductions,
            'net_total' => (string) $totals->net,
            'employer_total' => (string) $totals->employer,
            'branch_id' => (int) $totals->branches === 1 ? (int) $totals->branch : null,
        ])->save();
    }

    private function lockEditable(PayrollRun $run): PayrollRun
    {
        $run = PayrollRun::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($run->id);

        if (! $run->status->isEditable()) {
            throw ValidationException::withMessages(['run' => __('payroll_runs.validation.not_editable', ['status' => $run->status->label()])]);
        }

        return $run;
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function defaultName(PayrollPeriod $period, PayrollRunType $type, array $scope): string
    {
        return __('payroll_runs.runs.default_name', ['type' => $type->label(), 'period' => $period->name, 'scope' => $this->population->describe($scope)]);
    }

    private function completeSettlementItem(PayrollRun $run, User $user): void
    {
        $item = OffboardingItem::query()->where('offboarding_case_id', $run->offboarding_case_id)
            ->where('itemable_type', (new OffboardingCase)->getMorphClass())->where('itemable_id', $run->offboarding_case_id)
            ->whereNotIn('status', [TaskStatus::Done->value, TaskStatus::Skipped->value])->first();

        if ($item !== null) {
            $item->forceFill([
                'status' => TaskStatus::Done, 'completed_at' => now(), 'completed_by' => $user->id,
                'note' => __('payroll_runs.final_settlement.item_note', ['reference' => $run->reference], (string) setting('localisation.default_locale')),
            ])->save();
        }
    }

    private function notifyApprovers(PayrollRun $run): void
    {
        $approval = $run->latestApprovalRequest()->first();

        if ($approval === null) {
            return;
        }

        $employeeIds = $run->items()->withoutGlobalScopes()->pluck('employee_id')->all();

        foreach ($this->recipients->for($approval) as $approver) {
            if ($run->type !== PayrollRunType::Regular && in_array($approver->employee_id, $employeeIds, true)) {
                continue;
            }

            $approver->notify(new PayrollRunNotification($run, 'awaiting_approval'));
        }
    }

    private function notifyFinalised(PayrollRun $run): void
    {
        foreach (User::query()->whereIn('id', array_unique(array_filter([$run->submitted_by, $run->created_by])))->get() as $user) {
            $user->notify(new PayrollRunNotification($run, 'finalised'));
        }

        $payslips = Payslip::query()->withoutGlobalScopes()->where('payroll_run_id', $run->id)->get()->keyBy('employee_id');

        foreach (User::query()->whereIn('employee_id', $payslips->keys())->get() as $user) {
            $user->notify(new PayslipIssuedNotification($payslips[$user->employee_id]));
        }
    }
}
