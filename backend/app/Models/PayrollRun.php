<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use App\Enums\PayrollRunType;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasDataScope;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A payroll run (settlement) of a period for a population chosen by scope
 * (branches, departments, teams, positions, groups, individuals or the whole
 * company, with include / exclude lists). Written only by
 * App\Actions\Payroll\ManagePayrollRun; every transition is audited
 * ("payroll.run_*"). Once finalised it is never updated again except to be
 * marked reversed by a reversal run.
 *
 * Data scope: a run is visible to a restricted admin when it contains at
 * least one employee of their branches / departments / groups; inside it they
 * see only those employees (PayrollItem has its own scope).
 */
#[Fillable(['reference', 'payroll_period_id', 'type', 'name', 'status', 'scope', 'scope_summary', 'branch_id', 'corrects_run_id', 'offboarding_case_id', 'currency', 'notes', 'created_by'])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasApprovals, HasDataScope, HasFactory;

    public const WORKFLOW = 'payroll_run';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PayrollRunType::class,
            'status' => PayrollRunStatus::class,
            'scope' => 'array',
            'employee_count' => 'integer',
            'gross_total' => 'decimal:2',
            'deductions_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'employer_total' => 'decimal:2',
            'progress_total' => 'integer',
            'progress_done' => 'integer',
            'calculated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'finalised_at' => 'datetime',
            'reversed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'items.branch_id',
            'department' => 'items.department_id',
            'employee_group' => 'items.employee.groups.id',
        ];
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isFinalised(): bool
    {
        return $this->status === PayrollRunStatus::Finalised;
    }

    public function progressPercent(): int
    {
        return $this->progress_total > 0 ? (int) floor($this->progress_done * 100 / $this->progress_total) : 0;
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * @return HasMany<PayrollItemLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollItemLine::class);
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function correctsRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'corrects_run_id');
    }

    /**
     * The reversal run created for this run, if it was reversed.
     *
     * @return HasOne<PayrollRun, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(PayrollRun::class, 'corrects_run_id')->where('type', PayrollRunType::Reversal);
    }

    /**
     * @return BelongsTo<OffboardingCase, $this>
     */
    public function offboardingCase(): BelongsTo
    {
        return $this->belongsTo(OffboardingCase::class);
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
    public function calculator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finaliser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalised_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
