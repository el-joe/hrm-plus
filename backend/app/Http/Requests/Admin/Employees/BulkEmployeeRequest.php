<?php

namespace App\Http\Requests\Admin\Employees;

use App\Actions\Employees\BulkUpdateEmployees;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Rules\ExistsInScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk operation on the selected employees. Ids are resolved through the
 * data-scoped query, so ids outside the admin's scope are simply not found.
 */
class BulkEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bulkUpdate', Employee::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $action = $this->input('action');

        return [
            'action' => ['required', Rule::in(BulkUpdateEmployees::ACTIONS)],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'branch_id' => [Rule::requiredIf($action === 'assign_branch'), 'nullable', 'integer', new ExistsInScope(Branch::class)],
            'department_id' => [Rule::requiredIf($action === 'assign_department'), 'nullable', 'integer', new ExistsInScope(Department::class)],
            'manager_id' => [Rule::requiredIf($action === 'assign_manager'), 'nullable', 'integer', new ExistsInScope(Employee::class)],
            'group_id' => [Rule::requiredIf(in_array($action, ['add_to_group', 'remove_from_group'], true)), 'nullable', 'integer', new ExistsInScope(EmployeeGroup::class)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return Collection<int, Employee>
     */
    public function employees(): Collection
    {
        return Employee::query()->whereKey($this->validated('ids'))->orderBy('employee_number')->get();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...trans('employees.fields'), 'ids' => __('employees.bulk.selection'), 'group_id' => __('employees.fields.groups')];
    }
}
