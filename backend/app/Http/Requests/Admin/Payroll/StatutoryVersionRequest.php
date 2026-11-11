<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Models\StatutoryRuleSet;
use Illuminate\Foundation\Http\FormRequest;

class StatutoryVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $set = $this->route('statutory_rule');

        return $set instanceof StatutoryRuleSet && $this->user()->can('update', $set);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date'],
            'employee_rate' => ['nullable', 'decimal:0,4', 'min:0', 'max:100'],
            'employer_rate' => ['nullable', 'decimal:0,4', 'min:0', 'max:100'],
            'salary_floor' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999'],
            'salary_ceiling' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999'],
            'annual_exemption' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'base_reductions' => ['nullable', 'array', 'max:40'],
            'base_reductions.*' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'deduct_employee_insurance' => ['nullable', 'boolean'],
            'brackets' => ['nullable', 'array', 'max:30'],
            'brackets.*.lower_bound' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999'],
            'brackets.*.upper_bound' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999'],
            'brackets.*.rate' => ['nullable', 'decimal:0,4', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll.fields');
    }
}
