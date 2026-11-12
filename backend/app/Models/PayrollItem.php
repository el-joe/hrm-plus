<?php

namespace App\Models;

use App\Enums\PayBasis;
use App\Enums\PayrollItemStatus;
use App\Models\Concerns\HasDataScope;
use Database\Factories\PayrollItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One employee in a payroll run: the result of App\Services\Payroll\PayrollCalculator
 * with its full snapshot (inputs, quantities, rule versions, lines with explanation)
 * and calculation_hash. pay_slot = "regular" reserves the employee's pay for the
 * period (unique per period, see the migration). Frozen once the run is finalised.
 *
 * Scoped by the placement at the period end (branch / department) and the employee's groups.
 */
class PayrollItem extends Model
{
    /** @use HasFactory<PayrollItemFactory> */
    use HasDataScope, HasFactory;

    public const PAY_SLOT = 'regular';

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayrollItemStatus::class,
            'pay_basis' => PayBasis::class,
            'moved_in_period' => 'boolean',
            'basic_salary' => 'decimal:2',
            'proration_factor' => 'decimal:6',
            'gross_pay' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'employer_contributions' => 'decimal:2',
            'taxable_base' => 'decimal:2',
            'insurable_base' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'inputs' => 'array',
            'quantities' => 'array',
            'rule_versions' => 'array',
            'warnings' => 'array',
            'has_blocking_warning' => 'boolean',
            'calculated_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'branch_id',
            'department' => 'department_id',
            'employee_group' => 'employee.groups.id',
        ];
    }

    public function isCalculated(): bool
    {
        return $this->status === PayrollItemStatus::Calculated;
    }

    /**
     * Whether a warning of the given code was raised.
     */
    public function hasWarning(string $code): bool
    {
        return collect($this->warnings ?? [])->contains('code', $code);
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /**
     * @return BelongsTo<PayrollPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<PayrollItemLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollItemLine::class)->orderBy('display_order')->orderBy('id');
    }

    /**
     * @return HasOne<Payslip, $this>
     */
    public function payslip(): HasOne
    {
        return $this->hasOne(Payslip::class);
    }

    /**
     * @return HasMany<PayrollAdjustment, $this>
     */
    public function carriedAdjustments(): HasMany
    {
        return $this->hasMany(PayrollAdjustment::class, 'payroll_item_id');
    }
}
