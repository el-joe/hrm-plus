<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Enums\SalaryStructureScope;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobGrade;
use App\Models\SalaryStructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Assign a structure (salary_structures.update). An employee target also
 * needs employee_financials.update on that employee; targets must be inside
 * the admin's data scope.
 */
class StructureAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $structure = $this->route('salary_structure');

        return $structure instanceof SalaryStructure && $this->user()->can('update', $structure);
    }

    /**
     * The form sends one record select per scope (employee_id, job_grade_id, department_id, branch_id).
     */
    protected function prepareForValidation(): void
    {
        $field = [
            SalaryStructureScope::Employee->value => 'employee_id',
            SalaryStructureScope::JobGrade->value => 'job_grade_id',
            SalaryStructureScope::Department->value => 'department_id',
            SalaryStructureScope::Branch->value => 'branch_id',
        ][(string) $this->input('scope_type')] ?? null;

        if ($field !== null && ! $this->filled('scope_id')) {
            $this->merge(['scope_id' => $this->input($field)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope_type' => ['required', Rule::enum(SalaryStructureScope::class)],
            'scope_id' => ['nullable', 'integer', Rule::requiredIf(fn (): bool => $this->input('scope_type') !== SalaryStructureScope::Company->value)],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'return_to' => ['nullable', 'in:structure,employee'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $scope = SalaryStructureScope::tryFrom((string) $this->input('scope_type'));

            if ($scope === null || $scope === SalaryStructureScope::Company) {
                if ($scope === SalaryStructureScope::Company && $this->user()->hasDataRestrictions()) {
                    $validator->errors()->add('scope_type', __('payroll.validation.company_scope_restricted'));
                }

                return;
            }

            $class = match ($scope) {
                SalaryStructureScope::Employee => Employee::class,
                SalaryStructureScope::JobGrade => JobGrade::class,
                SalaryStructureScope::Department => Department::class,
                SalaryStructureScope::Branch => Branch::class,
            };
            $record = $class::query()->find($this->input('scope_id'));

            if ($record === null) {
                $validator->errors()->add('scope_id', __('payroll.validation.target_required'));
            } elseif ($record instanceof Employee && ! $this->user()->can('updateFinancial', $record)) {
                $validator->errors()->add('scope_id', __('payroll.validation.employee_financial_required'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll.fields');
    }
}
