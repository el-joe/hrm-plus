<?php

namespace App\Http\Requests\Admin\Employees;

use App\Enums\TerminationType;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TerminateEmployeeRequest extends FormRequest
{
    public function employee(): Employee
    {
        return $this->route('employee');
    }

    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->employee()) && ! $this->employee()->isTerminated();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'termination_date' => ['required', 'date', 'after_or_equal:'.$this->employee()->hire_date->toDateString(), 'before_or_equal:+1 year'],
            'termination_type' => ['required', Rule::enum(TerminationType::class)],
            'reason' => ['required', 'string', 'max:1000'],
            'eligible_for_rehire' => ['sometimes', 'boolean'],
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
