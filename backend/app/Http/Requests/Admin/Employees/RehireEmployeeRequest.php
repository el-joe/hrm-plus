<?php

namespace App\Http\Requests\Admin\Employees;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RehireEmployeeRequest extends FormRequest
{
    use ValidatesPlacement;

    public function employee(): Employee
    {
        return $this->route('employee');
    }

    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->employee()) && $this->employee()->isTerminated();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rehire_date' => ['required', 'date', 'after:'.$this->employee()->termination_date?->toDateString(), 'before_or_equal:+1 year'],
            'reason' => ['nullable', 'string', 'max:1000'],
            ...$this->placementRules($this->employee()),
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        // A terminated employee no longer holds a seat, so their old position counts like any other.
        return [fn (Validator $validator) => $this->validatePlacement($validator, null)];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('employees.fields');
    }
}
