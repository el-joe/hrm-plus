<?php

namespace App\Models;

use App\Enums\PayCalculationType;
use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayRounding;
use App\Enums\StatutoryType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\SalaryComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry of the salary component catalogue (basic salary, housing,
 * overtime, late penalty, social insurance, ...). The calculation type is a
 * closed list of rule types (App\Enums\PayCalculationType) — nothing is ever
 * evaluated. Read by payroll through App\Services\Payroll\PayrollInputs.
 */
#[Fillable([
    'code', 'name_ar', 'name_en', 'description', 'kind', 'category', 'calculation_type', 'amount', 'rate', 'base_component_id',
    'use_policy_multiplier', 'statutory_type', 'is_taxable', 'is_insurable', 'prorate_partial_period', 'requires_approval',
    'show_on_payslip', 'rounding', 'rounding_precision', 'display_order', 'is_active',
])]
class SalaryComponent extends Model implements GuardsDeletion
{
    /** @use HasFactory<SalaryComponentFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PayComponentKind::class,
            'category' => PayComponentCategory::class,
            'calculation_type' => PayCalculationType::class,
            'amount' => 'decimal:4',
            'rate' => 'decimal:4',
            'use_policy_multiplier' => 'boolean',
            'statutory_type' => StatutoryType::class,
            'is_taxable' => 'boolean',
            'is_insurable' => 'boolean',
            'prorate_partial_period' => 'boolean',
            'requires_approval' => 'boolean',
            'show_on_payslip' => 'boolean',
            'rounding' => PayRounding::class,
            'rounding_precision' => 'integer',
            'display_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SalaryComponent, $this>
     */
    public function baseComponent(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'base_component_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('display_order')->orderBy('code');
    }

    /**
     * Components that may be used on allowance (earning) or deduction entries.
     *
     * @param  Builder<static>  $query
     */
    public function scopeForEntries(Builder $query, PayComponentKind $kind): void
    {
        $query->where('kind', $kind)->whereIn('calculation_type', [
            PayCalculationType::Fixed, PayCalculationType::Percentage, PayCalculationType::Variable,
        ]);
    }

    /**
     * Effective rounding precision (the component's, else setting payroll.rounding_precision).
     */
    public function precision(): int
    {
        return $this->rounding_precision ?? (int) setting('payroll.rounding_precision');
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'salary_structure_lines' => SalaryStructureLine::query()->where('salary_component_id', $this->id)->count(),
            'pay_entries' => PayEntry::query()->withoutGlobalScopes()->where('salary_component_id', $this->id)->count(),
            'salary_components' => SalaryComponent::query()->where('base_component_id', $this->id)->count()
                + SalaryStructureLine::query()->where('base_component_id', $this->id)->count(),
        ];
    }
}
