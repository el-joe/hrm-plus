<?php

namespace App\Models;

use App\Enums\LeaveAccrualMethod;
use App\Enums\LeavePolicyScope;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\LeavePolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Effective-dated entitlement rules of one leave type for the whole company,
 * an employment type or an employee group: yearly entitlement and accrual
 * method, carry-over, ceiling, negative balance and encashment. Resolved by
 * App\Services\Leave\LeavePolicyResolver (group > employment type > company,
 * then the latest effective_from).
 */
#[Fillable([
    'code', 'name_ar', 'name_en', 'leave_type_id', 'scope_type', 'scope_id', 'effective_from', 'effective_to',
    'entitlement_days', 'accrual_method', 'service_tiers', 'carry_over_max_days', 'carry_over_expiry_months',
    'max_balance_days', 'allow_negative_balance', 'negative_limit_days', 'encashable', 'encashment_max_days',
    'description', 'is_active',
])]
class LeavePolicy extends Model implements GuardsDeletion
{
    /** @use HasFactory<LeavePolicyFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => LeavePolicyScope::class,
            'scope_id' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'entitlement_days' => 'decimal:2',
            'accrual_method' => LeaveAccrualMethod::class,
            'service_tiers' => 'array',
            'carry_over_max_days' => 'decimal:2',
            'carry_over_expiry_months' => 'integer',
            'max_balance_days' => 'decimal:2',
            'allow_negative_balance' => 'boolean',
            'negative_limit_days' => 'decimal:2',
            'encashable' => 'boolean',
            'encashment_max_days' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class, 'scope_id');
    }

    /**
     * @return BelongsTo<EmployeeGroup, $this>
     */
    public function employeeGroup(): BelongsTo
    {
        return $this->belongsTo(EmployeeGroup::class, 'scope_id');
    }

    /**
     * Policies in force on the date (Y-m-d).
     *
     * @param  Builder<static>  $query
     */
    public function scopeEffectiveOn(Builder $query, string $date): void
    {
        $query->whereDate('effective_from', '<=', $date)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date));
    }

    /**
     * "Company", "Full-time", "Group: Field staff".
     */
    public function scopeLabel(): string
    {
        return match ($this->scope_type) {
            LeavePolicyScope::Company => __('leave.enums.policy_scopes.company'),
            LeavePolicyScope::EmploymentType => $this->scope_type->label().': '.($this->employmentType?->name ?? '#'.$this->scope_id),
            LeavePolicyScope::EmployeeGroup => $this->scope_type->label().': '.($this->employeeGroup?->name ?? '#'.$this->scope_id),
        };
    }

    /**
     * Tiers sorted by min_years: list of {min_years:int, days:string}.
     *
     * @return list<array{min_years: int, days: string}>
     */
    public function tiers(): array
    {
        $tiers = array_map(fn (array $tier): array => [
            'min_years' => (int) ($tier['min_years'] ?? 0),
            'days' => number_format((float) ($tier['days'] ?? 0), 2, '.', ''),
        ], (array) $this->service_tiers);

        usort($tiers, fn (array $a, array $b): int => $a['min_years'] <=> $b['min_years']);

        return $tiers;
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'leave_balance_transactions' => LeaveBalanceTransaction::query()->withoutGlobalScopes()->where('leave_policy_id', $this->id)->count(),
        ];
    }
}
