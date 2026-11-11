<?php

namespace App\Actions\Payroll;

use App\Enums\ApprovalActionType;
use App\Enums\ApprovalStatus;
use App\Enums\PayComponentKind;
use App\Enums\PayEntryCalculation;
use App\Enums\PayEntryRecurrence;
use App\Enums\PayEntryTarget;
use App\Enums\RequestStatus;
use App\Exceptions\ApprovalException;
use App\Models\ApprovalRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\PayEntry;
use App\Models\PayEntryBatch;
use App\Models\SalaryComponent;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Audit\AuditLogger;
use App\Services\Payroll\PayrollFileStore;
use App\Services\Payroll\PayrollReferences;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The life of an allowance or deduction entry.
 *
 * create(): one entry for an employee, group, department, branch or the
 *   company. When the component requires approval the entry is pending and
 *   goes through the "payroll_allowance" / "payroll_deduction" workflow;
 *   otherwise it is approved at once by its author.
 * bulk():   the same entry for many employees at once (one entry each, one batch).
 * decide(): one approval step; the outcome is applied by applyApprovalOutcome()
 *   (listener App\Listeners\SyncPayrollApprovals).
 * cancel(): a pending entry, or an approved one that has not started yet.
 * stop():   end an approved recurring / instalment entry after a date (history kept).
 *
 * Approved entries are never edited: payroll history must keep what it used.
 */
class ManagePayEntry
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly PayrollReferences $references,
        private readonly PayrollFileStore $files,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  salary_component_id, target_type, target_id, calculation, amount, rate, base_component_id,
     *                                      recurrence, start_date, end_date, total_amount, instalment_count, is_taxable, is_insurable,
     *                                      prorate, reason, notes
     * @param  list<UploadedFile>  $attachments
     *
     * @throws ValidationException
     */
    public function create(PayComponentKind $kind, array $data, User $user, array $attachments = [], ?Model $source = null, ?PayEntryBatch $batch = null): PayEntry
    {
        $component = $this->component($kind, $data);
        $attributes = $this->attributes($kind, $component, $data);

        $entry = DB::transaction(function () use ($kind, $component, $attributes, $user, $attachments, $source, $batch): PayEntry {
            $entry = PayEntry::create([
                ...$attributes,
                'reference' => $this->references->next($kind->value),
                'kind' => $kind,
                'salary_component_id' => $component->id,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'pay_entry_batch_id' => $batch?->id,
                'created_by' => $user->id,
            ]);

            foreach ($attachments as $file) {
                $entry->attachments()->create([...$this->files->storeEntryFile($file), 'uploaded_by' => $user->id]);
            }

            if ($component->requires_approval) {
                try {
                    $this->approvals->submit($entry, $entry->workflowKey(), $user);
                } catch (ApprovalException $exception) {
                    throw ValidationException::withMessages(['salary_component_id' => $exception->getMessage()]);
                }
            } else {
                $entry->forceFill(['status' => RequestStatus::Approved, 'decided_at' => now(), 'decided_by' => $user->id])->save();
            }

            $this->audit->log('payroll.entry_created', $entry, meta: [
                'kind' => $kind->value, 'component' => $component->code, 'target' => $entry->target_type->value, 'recurrence' => $entry->recurrence->value,
            ], actor: $user);

            return $entry;
        });

        return $entry->refresh();
    }

    /**
     * One entry per employee, in one batch, all or nothing.
     *
     * @param  array<string, mixed>  $data  as create() without the target
     * @param  list<int>  $employeeIds
     *
     * @throws ValidationException
     */
    public function bulk(PayComponentKind $kind, array $data, array $employeeIds, User $user, ?string $note = null, string $source = 'bulk', ?string $fileName = null): PayEntryBatch
    {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));

        if ($employeeIds === []) {
            throw ValidationException::withMessages(['employee_ids' => __('payroll.validation.no_employees')]);
        }

        $visible = Employee::query()->whereKey($employeeIds)->pluck('id')->all();

        if (count($visible) !== count($employeeIds)) {
            throw ValidationException::withMessages(['employee_ids' => __('payroll.validation.employees_out_of_scope')]);
        }

        return DB::transaction(function () use ($kind, $data, $employeeIds, $user, $note, $source, $fileName): PayEntryBatch {
            $batch = PayEntryBatch::create([
                'source' => $source, 'kind' => $kind, 'entries_count' => count($employeeIds), 'file_name' => $fileName, 'note' => $note, 'created_by' => $user->id,
            ]);

            foreach ($employeeIds as $employeeId) {
                $this->create($kind, [...$data, 'target_type' => PayEntryTarget::Employee->value, 'target_id' => $employeeId], $user, batch: $batch);
            }

            $this->audit->log('payroll.entries_bulk_created', null, meta: ['batch' => $batch->id, 'kind' => $kind->value, 'count' => count($employeeIds), 'source' => $source], actor: $user);

            return $batch;
        });
    }

    /**
     * @throws ValidationException
     */
    public function decide(PayEntry $entry, User $approver, bool $approve, ?string $reason = null): void
    {
        $approval = $entry->latestApprovalRequest;

        if ($approval === null || ! $approval->isPending() || ! $entry->isPending()) {
            throw ValidationException::withMessages(['reason' => __('payroll.validation.not_pending')]);
        }

        if ($entry->employee_id !== null && $approver->employee_id === $entry->employee_id) {
            throw ValidationException::withMessages(['reason' => __('payroll.validation.own_entry')]);
        }

        try {
            $approve ? $this->approvals->approve($approval, $approver, $reason) : $this->approvals->reject($approval, $approver, (string) $reason);
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }
    }

    public function applyApprovalOutcome(PayEntry $entry, bool $approved, ?User $decidedBy, ?string $reason): void
    {
        if (! $entry->isPending()) {
            return;
        }

        $entry->forceFill([
            'status' => $approved ? RequestStatus::Approved : RequestStatus::Rejected,
            'decided_at' => now(),
            'decided_by' => $decidedBy?->id,
            'rejection_reason' => $approved ? null : $reason,
        ])->save();
    }

    /**
     * @throws ValidationException
     */
    public function cancel(PayEntry $entry, User $user, ?string $reason): void
    {
        $started = ! $entry->start_date->isFuture();

        if (! in_array($entry->status, [RequestStatus::Pending, RequestStatus::Approved], true) || ($entry->status === RequestStatus::Approved && $started)) {
            throw ValidationException::withMessages(['reason' => __('payroll.validation.not_cancellable')]);
        }

        DB::transaction(function () use ($entry, $user, $reason): void {
            $this->closeApproval($entry->latestApprovalRequest, $user, $reason);
            $entry->forceFill([
                'status' => RequestStatus::Cancelled, 'cancelled_at' => now(), 'cancelled_by' => $user->id, 'cancellation_reason' => $reason,
            ])->save();
        });
    }

    /**
     * End an approved recurring / instalment entry: the last day it applies (history kept).
     *
     * @throws ValidationException
     */
    public function stop(PayEntry $entry, string $lastDay, User $user, ?string $reason): void
    {
        $last = CarbonImmutable::parse($lastDay)->startOfDay();

        if ($entry->status !== RequestStatus::Approved || $entry->recurrence === PayEntryRecurrence::OneTime) {
            throw ValidationException::withMessages(['end_date' => __('payroll.validation.not_stoppable')]);
        }

        if ($last->lessThan($entry->start_date) || ($entry->end_date !== null && $last->greaterThanOrEqualTo($entry->end_date))) {
            throw ValidationException::withMessages(['end_date' => __('payroll.validation.stop_date')]);
        }

        $entry->forceFill(['end_date' => $last->toDateString(), 'cancellation_reason' => $reason])->save();
        $this->audit->log('payroll.entry_stopped', $entry, meta: ['end_date' => $last->toDateString()], actor: $user);
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function addAttachments(PayEntry $entry, array $files, User $user): void
    {
        foreach ($files as $file) {
            $entry->attachments()->create([...$this->files->storeEntryFile($file), 'uploaded_by' => $user->id]);
        }

        $this->audit->log('payroll.entry_attachments_added', $entry, meta: ['count' => count($files)], actor: $user);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function component(PayComponentKind $kind, array $data): SalaryComponent
    {
        $component = SalaryComponent::query()->active()->forEntries($kind)->find($data['salary_component_id'] ?? null);

        if ($component === null) {
            throw ValidationException::withMessages(['salary_component_id' => __('payroll.validation.entry_component')]);
        }

        return $component;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function attributes(PayComponentKind $kind, SalaryComponent $component, array $data): array
    {
        $target = PayEntryTarget::from($data['target_type']);
        $recurrence = PayEntryRecurrence::from($data['recurrence']);
        $calculation = PayEntryCalculation::from($data['calculation'] ?? 'fixed');
        $start = CarbonImmutable::parse($data['start_date'])->startOfDay();

        if ($recurrence === PayEntryRecurrence::Instalment && $kind !== PayComponentKind::Deduction) {
            throw ValidationException::withMessages(['recurrence' => __('payroll.validation.instalment_deductions_only')]);
        }

        if ($kind === PayComponentKind::Deduction && blank($data['reason'] ?? null)) {
            throw ValidationException::withMessages(['reason' => __('payroll.validation.reason_required')]);
        }

        $attributes = [
            ...$this->target($target, $data['target_id'] ?? null),
            'calculation' => $calculation,
            'amount' => null,
            'rate' => null,
            'base_component_id' => null,
            'recurrence' => $recurrence,
            'start_date' => $start->toDateString(),
            'end_date' => null,
            'total_amount' => null,
            'instalment_count' => null,
            'is_taxable' => $this->nullableBool($data['is_taxable'] ?? null),
            'is_insurable' => $this->nullableBool($data['is_insurable'] ?? null),
            'prorate' => $recurrence === PayEntryRecurrence::Recurring ? $this->nullableBool($data['prorate'] ?? null) : false,
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        if ($recurrence === PayEntryRecurrence::Instalment) {
            $count = (int) ($data['instalment_count'] ?? 0);
            $total = (string) ($data['total_amount'] ?? '0');

            if ($count < 1 || bccomp($total, '0', 2) <= 0) {
                throw ValidationException::withMessages(['total_amount' => __('payroll.validation.instalment_plan')]);
            }

            $attributes['calculation'] = PayEntryCalculation::Fixed;
            $attributes['total_amount'] = bcadd($total, '0', 2);
            $attributes['instalment_count'] = $count;
            $attributes['amount'] = bcdiv($total, (string) $count, 2);
            $attributes['end_date'] = $start->addMonthsNoOverflow($count - 1)->toDateString();

            return $attributes;
        }

        if ($calculation === PayEntryCalculation::Fixed) {
            if (! filled($data['amount'] ?? null) || bccomp((string) $data['amount'], '0', 2) <= 0) {
                throw ValidationException::withMessages(['amount' => __('payroll.validation.amount_required')]);
            }

            $attributes['amount'] = bcadd((string) $data['amount'], '0', 2);
        } else {
            if (! filled($data['rate'] ?? null) || bccomp((string) $data['rate'], '0', 4) <= 0) {
                throw ValidationException::withMessages(['rate' => __('payroll.validation.rate_required')]);
            }

            $attributes['rate'] = bcadd((string) $data['rate'], '0', 4);
            $attributes['base_component_id'] = filled($data['base_component_id'] ?? null) ? (int) $data['base_component_id'] : null;

            if ($attributes['base_component_id'] === $component->id) {
                throw ValidationException::withMessages(['base_component_id' => __('payroll.validation.base_cycle')]);
            }
        }

        if ($recurrence === PayEntryRecurrence::OneTime) {
            $attributes['end_date'] = $start->toDateString();
        } elseif (filled($data['end_date'] ?? null)) {
            $end = CarbonImmutable::parse($data['end_date']);

            if ($end->lessThan($start)) {
                throw ValidationException::withMessages(['end_date' => __('payroll.validation.end_before_start')]);
            }

            $attributes['end_date'] = $end->toDateString();
        }

        return $attributes;
    }

    /**
     * Target columns: the target id plus the branch / department that carry the data scope.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function target(PayEntryTarget $target, mixed $id): array
    {
        $empty = ['target_type' => $target, 'employee_id' => null, 'employee_group_id' => null, 'department_id' => null, 'branch_id' => null];

        if ($target === PayEntryTarget::Company) {
            return $empty;
        }

        $class = match ($target) {
            PayEntryTarget::Employee => Employee::class,
            PayEntryTarget::EmployeeGroup => EmployeeGroup::class,
            PayEntryTarget::Department => Department::class,
            PayEntryTarget::Branch => Branch::class,
        };

        // Visible to the admin (global data scope in the admin area).
        $record = filled($id) ? $class::query()->find($id) : null;

        if ($record === null) {
            throw ValidationException::withMessages(['target_id' => __('payroll.validation.target_required')]);
        }

        return match ($target) {
            PayEntryTarget::Employee => [...$empty, 'employee_id' => $record->id, 'branch_id' => $record->branch_id, 'department_id' => $record->department_id],
            PayEntryTarget::EmployeeGroup => [...$empty, 'employee_group_id' => $record->id],
            PayEntryTarget::Department => [...$empty, 'department_id' => $record->id, 'branch_id' => $record->branch_id],
            PayEntryTarget::Branch => [...$empty, 'branch_id' => $record->id],
        };
    }

    private function nullableBool(mixed $value): ?bool
    {
        return $value === null || $value === '' ? null : filter_var($value, FILTER_VALIDATE_BOOLEAN);
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
}
