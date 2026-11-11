<?php

namespace App\Http\Requests\Admin\Employees;

use App\Enums\AssignmentChangeType;
use App\Models\Employee;
use App\Services\Employees\EmployeePlacement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Transfer / promotion / reassignment / manager change of an employed employee.
 */
class AssignmentChangeRequest extends FormRequest
{
    use ValidatesPlacement;

    public const TYPES = [
        AssignmentChangeType::Transfer,
        AssignmentChangeType::Promotion,
        AssignmentChangeType::Reassignment,
        AssignmentChangeType::ManagerChange,
    ];

    public function employee(): Employee
    {
        return $this->route('employee');
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->employee()) && ! $this->employee()->isTerminated();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'change_type' => ['required', Rule::in(array_map(fn (AssignmentChangeType $type) => $type->value, self::TYPES))],
            'effective_date' => ['required', 'date', 'after_or_equal:'.$this->employee()->original_hire_date->toDateString(), 'before_or_equal:+1 year'],
            'reason' => ['nullable', 'string', 'max:1000'],
            ...$this->placementRules($this->employee()),
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validatePlacement($validator, $this->employee());

                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $employee = $this->employee();
                $new = app(EmployeePlacement::class)->complete($this->placement(), $employee);
                $changed = collect($new)->contains(fn ($value, string $column): bool => (string) $value !== (string) $employee->getAttribute($column));

                if (! $changed) {
                    $validator->errors()->add('change_type', __('employees.validation.nothing_changed'));
                }
            },
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
