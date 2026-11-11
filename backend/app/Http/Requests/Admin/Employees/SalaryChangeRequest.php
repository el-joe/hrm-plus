<?php

namespace App\Http\Requests\Admin\Employees;

use App\Enums\PayBasis;
use App\Enums\SalaryChangeType;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalaryChangeRequest extends FormRequest
{
    public function employee(): Employee
    {
        return $this->route('employee');
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateFinancial', $this->employee());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'basic_salary' => ['required', 'decimal:0,2', 'min:0', 'max:999999999999.99'],
            'effective_date' => ['required', 'date', 'after_or_equal:'.$this->employee()->original_hire_date->toDateString(), 'before_or_equal:+1 year'],
            'change_type' => ['nullable', Rule::in([SalaryChangeType::Promotion->value, SalaryChangeType::Adjustment->value])],
            'pay_basis' => ['nullable', Rule::enum(PayBasis::class)],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('employees.fields');
    }
}
