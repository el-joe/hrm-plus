<?php

namespace App\Services\Payroll;

use App\Enums\PayComponentKind;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\JobGrade;
use App\Models\SalaryComponent;
use Illuminate\Support\Collection;

/**
 * Option lists of the payroll forms. Inside the admin area the global data
 * scope limits employees, departments and branches to what the admin sees.
 */
class PayrollFormOptions
{
    /**
     * @return array<string, Collection<int, mixed>>
     */
    public function targets(): array
    {
        return [
            'employees' => $this->employees(),
            'groups' => EmployeeGroup::query()->active()->orderBy('code')->get(),
            'departments' => Department::query()->active()->orderBy('code')->get(),
            'branches' => Branch::query()->active()->orderBy('code')->get(),
            'grades' => JobGrade::query()->active()->orderBy('level')->get(),
        ];
    }

    /**
     * Employed employees (active or inactive, not terminated) by number.
     *
     * @return Collection<int, Employee>
     */
    public function employees(): Collection
    {
        return Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en', 'branch_id', 'department_id']);
    }

    /**
     * Components usable on entries of a kind.
     *
     * @return Collection<int, SalaryComponent>
     */
    public function entryComponents(PayComponentKind $kind): Collection
    {
        return SalaryComponent::query()->active()->forEntries($kind)->ordered()->get();
    }

    /**
     * Components usable as structure lines (keeps inactive ones already used).
     *
     * @param  list<int>  $keep
     * @return Collection<int, SalaryComponent>
     */
    public function structureComponents(array $keep = []): Collection
    {
        return SalaryComponent::query()
            ->where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $keep ?: [0]))
            ->where('calculation_type', '!=', 'variable')
            ->ordered()->get();
    }
}
