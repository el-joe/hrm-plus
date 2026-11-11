<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Enums\PayCalculationType;
use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayRounding;
use App\Enums\StatutoryType;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\SalaryComponent;
use Illuminate\Validation\Rule;

class SalaryComponentRequest extends StructureRequest
{
    public const BOOLEANS = ['use_policy_multiplier', 'is_taxable', 'is_insurable', 'prorate_partial_period', 'requires_approval', 'show_on_payslip', 'is_active'];

    protected function modelClass(): string
    {
        return SalaryComponent::class;
    }

    protected function routeKey(): string
    {
        return 'salary_component';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'description' => ['nullable', 'string', 'max:1000'],
            'kind' => ['required', Rule::enum(PayComponentKind::class)],
            'category' => ['required', Rule::enum(PayComponentCategory::class)],
            'calculation_type' => ['required', Rule::enum(PayCalculationType::class)],
            'amount' => ['nullable', 'decimal:0,4', 'min:0', 'max:9999999999'],
            'rate' => ['nullable', 'decimal:0,4', 'min:0', 'max:99999'],
            'base_component_id' => ['nullable', 'integer', Rule::exists(SalaryComponent::class, 'id')],
            'statutory_type' => ['nullable', Rule::enum(StatutoryType::class)],
            'rounding' => ['required', Rule::enum(PayRounding::class)],
            'rounding_precision' => ['nullable', 'integer', 'between:0,4'],
            'display_order' => ['required', 'integer', 'between:0,9999'],
            ...array_fill_keys(array_diff(self::BOOLEANS, ['is_active']), ['sometimes', 'boolean']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(self::BOOLEANS);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll.fields');
    }
}
