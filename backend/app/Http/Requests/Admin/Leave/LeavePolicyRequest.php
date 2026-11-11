<?php

namespace App\Http\Requests\Admin\Leave;

use App\Enums\LeaveAccrualMethod;
use App\Enums\LeavePolicyScope;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Validation\Rule;

class LeavePolicyRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return LeavePolicy::class;
    }

    protected function routeKey(): string
    {
        return 'leave_policy';
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->input('scope_type') === LeavePolicyScope::Company->value) {
            $this->merge(['scope_id' => null]);
        }

        // Tier rows left empty in the form are ignored.
        $tiers = array_values(array_filter((array) $this->input('service_tiers', []), fn ($tier): bool => is_array($tier)
            && filled($tier['min_years'] ?? null) && filled($tier['days'] ?? null)));
        $this->merge(['service_tiers' => $tiers]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $scope = $this->input('scope_type');

        return [
            ...$this->identityRules(),
            'leave_type_id' => ['required', 'integer', Rule::exists(LeaveType::class, 'id')],
            'scope_type' => ['required', Rule::enum(LeavePolicyScope::class)],
            'scope_id' => [
                Rule::requiredIf($scope !== LeavePolicyScope::Company->value), 'nullable', 'integer',
                ...match ($scope) {
                    LeavePolicyScope::EmploymentType->value => [$this->inScope(EmploymentType::class, 'scope_id')],
                    LeavePolicyScope::EmployeeGroup->value => [$this->inScope(EmployeeGroup::class, 'scope_id')],
                    default => [],
                },
            ],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'entitlement_days' => ['required', 'decimal:0,2', 'min:0', 'max:366'],
            'accrual_method' => ['required', Rule::enum(LeaveAccrualMethod::class)],
            'service_tiers' => [Rule::requiredIf($this->input('accrual_method') === LeaveAccrualMethod::ServiceTiers->value), 'array', 'max:10'],
            'service_tiers.*.min_years' => ['required', 'integer', 'between:0,60', 'distinct'],
            'service_tiers.*.days' => ['required', 'decimal:0,2', 'min:0', 'max:366'],
            'carry_over_max_days' => ['nullable', 'decimal:0,2', 'min:0', 'max:366'],
            'carry_over_expiry_months' => ['nullable', 'integer', 'between:1,24'],
            'max_balance_days' => ['nullable', 'decimal:0,2', 'gt:0', 'max:999'],
            'allow_negative_balance' => ['sometimes', 'boolean'],
            'negative_limit_days' => ['nullable', 'decimal:0,2', 'min:0', 'max:366'],
            'encashable' => ['sometimes', 'boolean'],
            'encashment_max_days' => ['nullable', 'decimal:0,2', 'min:0', 'max:366'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        $data = $this->withBooleans(['allow_negative_balance', 'encashable', 'is_active']);
        $data['service_tiers'] = ($data['accrual_method'] ?? null) === LeaveAccrualMethod::ServiceTiers->value
            ? array_map(fn (array $tier): array => ['min_years' => (int) $tier['min_years'], 'days' => bcadd((string) $tier['days'], '0', 2)], $data['service_tiers'] ?? [])
            : null;

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('leave.fields');
    }
}
