<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Models\Employee;
use App\Models\PayrollAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A manual post-finalisation adjustment: employee, component (or plain earning /
 * deduction), signed amount (a negative deduction is a refund), the period it
 * corrects and a mandatory reason.
 */
class PayrollAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PayrollAdjustment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer'],
            'salary_component_id' => ['nullable', 'integer', Rule::exists('salary_components', 'id')],
            'kind' => ['required_without:salary_component_id', 'nullable', Rule::in(['earning', 'deduction'])],
            'amount' => ['required', 'decimal:0,2', 'not_in:0', 'min:-99999999', 'max:99999999'],
            'original_period_id' => ['nullable', 'integer', Rule::exists('payroll_periods', 'id')],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function employee(): Employee
    {
        $employee = Employee::query()->find($this->validated('employee_id'));
        abort_if($employee === null || $employee->id === $this->user()->employee_id, 403);

        return $employee;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll_runs.corrections.fields');
    }
}
