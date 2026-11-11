<?php

namespace App\Models;

use App\Enums\PayComponentKind;
use App\Enums\PayEntryCalculation;
use App\Enums\PayEntryRecurrence;
use App\Enums\PayEntryTarget;
use App\Enums\RequestStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasDataScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An allowance (kind earning: allowances, bonuses, incentives, commissions)
 * or a deduction (penalties, recoveries, instalment deductions) for an
 * employee, employee group, department, branch or the whole company.
 *
 * Only approved entries reach payroll (App\Services\Payroll\PayEntryResolver).
 * Entries are never edited once approved: they are cancelled (stopped) and
 * replaced, so payroll history keeps the rows it used. Amounts are audited
 * with their values redacted.
 *
 * Data scope: branch_id / department_id / employee_group_id (for employee
 * targets the placement at creation).
 */
#[Fillable([
    'reference', 'kind', 'salary_component_id', 'target_type', 'employee_id', 'employee_group_id', 'department_id', 'branch_id',
    'calculation', 'amount', 'rate', 'base_component_id', 'recurrence', 'start_date', 'end_date', 'total_amount', 'instalment_count',
    'is_taxable', 'is_insurable', 'prorate', 'reason', 'notes', 'source_type', 'source_id', 'pay_entry_batch_id', 'created_by',
])]
class PayEntry extends Model
{
    use Auditable, HasApprovals, HasDataScope;

    public const WORKFLOWS = ['earning' => 'payroll_allowance', 'deduction' => 'payroll_deduction'];

    /**
     * @var list<string>
     */
    protected array $auditRedact = ['amount', 'rate', 'total_amount'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PayComponentKind::class,
            'target_type' => PayEntryTarget::class,
            'calculation' => PayEntryCalculation::class,
            'amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'recurrence' => PayEntryRecurrence::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'total_amount' => 'decimal:2',
            'instalment_count' => 'integer',
            'is_taxable' => 'boolean',
            'is_insurable' => 'boolean',
            'prorate' => 'boolean',
            'status' => RequestStatus::class,
            'decided_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return ['branch' => 'branch_id', 'department' => 'department_id', 'employee_group' => 'employee_group_id'];
    }

    /**
     * @return BelongsTo<SalaryComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }

    /**
     * @return BelongsTo<SalaryComponent, $this>
     */
    public function baseComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'base_component_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<EmployeeGroup, $this>
     */
    public function employeeGroup(): BelongsTo
    {
        return $this->belongsTo(EmployeeGroup::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<PayEntryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(PayEntryBatch::class, 'pay_entry_batch_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<PayEntryAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PayEntryAttachment::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOfKind(Builder $query, PayComponentKind $kind): void
    {
        $query->where($query->qualifyColumn('kind'), $kind);
    }

    /**
     * Approved entries with at least one day inside the period.
     *
     * @param  Builder<static>  $query
     */
    public function scopeActiveBetween(Builder $query, string $from, string $to): void
    {
        $query->where('status', RequestStatus::Approved)
            ->where('start_date', '<=', $to)
            ->where(fn (Builder $query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $from));
    }

    public function isPending(): bool
    {
        return $this->status === RequestStatus::Pending;
    }

    public function isEarning(): bool
    {
        return $this->kind === PayComponentKind::Earning;
    }

    /**
     * The workflow key used when the component requires approval.
     */
    public function workflowKey(): string
    {
        return self::WORKFLOWS[$this->kind->value];
    }

    /**
     * "Employee: EMP-00001 · Sara", "Department: IT · Information technology".
     */
    public function targetLabel(): string
    {
        $record = match ($this->target_type) {
            PayEntryTarget::Employee => $this->employee()->withoutGlobalScopes()->first(),
            PayEntryTarget::EmployeeGroup => $this->employeeGroup()->withoutGlobalScopes()->first(),
            PayEntryTarget::Department => $this->department()->withoutGlobalScopes()->first(),
            PayEntryTarget::Branch => $this->branch()->withoutGlobalScopes()->first(),
            PayEntryTarget::Company => null,
        };

        if ($record === null) {
            return $this->target_type->label();
        }

        $name = $record instanceof Employee ? $record->label() : trim(($record->code ?? '').' · '.$record->name, ' ·');

        return $this->target_type->label().': '.$name;
    }

    /**
     * Instalment deductions: amount of instalment n (1-based); the last one takes the rounding remainder.
     */
    public function instalmentAmount(int $number): string
    {
        $count = max(1, (int) $this->instalment_count);
        $regular = bcdiv((string) $this->total_amount, (string) $count, 2);

        return $number < $count ? $regular : bcsub((string) $this->total_amount, bcmul($regular, (string) ($count - 1), 2), 2);
    }

    /**
     * Instalment deductions: the due date of instalment n (month of start_date + n − 1, same day, clamped to the month end).
     */
    public function instalmentDueDate(int $number): CarbonInterface
    {
        return $this->start_date->toImmutable()->addMonthsNoOverflow($number - 1);
    }
}
