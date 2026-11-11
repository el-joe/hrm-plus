<?php

namespace App\Services\Employees;

use App\Enums\AssignmentChangeType;
use App\Enums\ContractType;
use App\Enums\DocumentCategory;
use App\Enums\EmployeeStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\TerminationType;
use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Models\JobGrade;
use App\Models\JobTitle;
use App\Models\Position;
use App\Models\Team;
use Illuminate\Database\Eloquent\Collection;

/**
 * Choices offered by the employee screens. Every query goes through
 * Eloquent, so a data-restricted admin is only offered records inside their
 * scope; inactive records are only listed when the employee already uses them.
 */
class EmployeeFormOptions
{
    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'branches' => Branch::query()->orderBy('code')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
            'jobTitles' => JobTitle::query()->orderBy('code')->get(),
            'employmentTypes' => EmploymentType::query()->orderBy('code')->get(),
            'groups' => EmployeeGroup::query()->orderBy('code')->get(),
            'statuses' => EmployeeStatus::cases(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bulk(): array
    {
        return [
            'branches' => Branch::query()->active()->orderBy('code')->get(),
            'departments' => Department::query()->active()->with('branch')->orderBy('code')->get(),
            'groups' => EmployeeGroup::query()->active()->orderBy('code')->get(),
            'managers' => $this->managers(null),
        ];
    }

    /**
     * Placement choices plus the personal-data enums, for create / edit / transfer / rehire.
     *
     * @return array<string, mixed>
     */
    public function form(?Employee $employee): array
    {
        $keep = fn (string $column) => $employee?->getAttribute($column);

        return [
            'branches' => Branch::query()->activeOrKeep($keep('branch_id'))->orderBy('code')->get(),
            'departments' => Department::query()->activeOrKeep($keep('department_id'))->with('branch')->orderBy('code')->get(),
            'teams' => Team::query()->activeOrKeep($keep('team_id'))->orderBy('code')->get(),
            'positions' => Position::query()->activeOrKeep($keep('position_id'))->withCount('holders')->orderBy('code')->get(),
            'jobTitles' => JobTitle::query()->activeOrKeep($keep('job_title_id'))->orderBy('code')->get(),
            'jobGrades' => JobGrade::query()->activeOrKeep($keep('job_grade_id'))->orderBy('level')->get(),
            'employmentTypes' => EmploymentType::query()->activeOrKeep($keep('employment_type_id'))->orderBy('code')->get(),
            'costCenters' => CostCenter::query()->activeOrKeep($keep('cost_center_id'))->orderBy('code')->get(),
            'locations' => BranchLocation::query()->activeOrKeep($keep('work_location_id'))->orderBy('branch_id')->orderBy('id')->get(),
            'managers' => $this->managers($employee),
            'groups' => EmployeeGroup::query()->where(fn ($query) => $query->where('is_active', true)
                ->when($employee?->exists, fn ($query) => $query->orWhereIn('id', $employee->groups()->pluck('employee_groups.id'))))
                ->orderBy('code')->get(),
            'genders' => Gender::cases(),
            'maritalStatuses' => MaritalStatus::cases(),
            'terminationTypes' => TerminationType::cases(),
            'contractTypes' => ContractType::cases(),
            'documentCategories' => DocumentCategory::cases(),
            'changeTypes' => [AssignmentChangeType::Transfer, AssignmentChangeType::Promotion, AssignmentChangeType::Reassignment, AssignmentChangeType::ManagerChange],
            'customFields' => app(CustomFieldValues::class)->activeFields(),
            'customValues' => $employee ? app(CustomFieldValues::class)->formValues($employee) : [],
        ];
    }

    /**
     * Active employees who can be picked as a manager (the employee's current manager is kept).
     *
     * @return Collection<int, Employee>
     */
    public function managers(?Employee $employee)
    {
        return Employee::query()
            ->where(fn ($query) => $query->where('status', EmployeeStatus::Active)
                ->when($employee?->manager_id, fn ($query, $id) => $query->orWhere('id', $id)))
            ->when($employee?->exists, fn ($query) => $query->whereKeyNot($employee->getKey()))
            ->orderBy('employee_number')
            ->get(['id', 'employee_number', 'name_ar', 'name_en', 'department_id']);
    }
}
