<?php

namespace App\Http\Requests\Admin\Employees;

use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\CostCenter;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\JobGrade;
use App\Models\JobTitle;
use App\Models\Position;
use App\Models\Team;
use App\Rules\ExistsInScope;
use App\Services\Employees\EmployeePlacement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Placement fields of the employee forms: each id must be a record the admin
 * can see (active, unless the employee already has it), and the combination
 * must pass App\Services\Employees\EmployeePlacement::errors().
 *
 * @mixin FormRequest
 */
trait ValidatesPlacement
{
    /**
     * @return array<string, list<mixed>>
     */
    protected function placementRules(?Employee $employee): array
    {
        $keep = fn (string $column) => $employee?->getAttribute($column);

        return [
            'branch_id' => ['required', 'integer', new ExistsInScope(Branch::class, keep: $keep('branch_id'))],
            'department_id' => ['required', 'integer', new ExistsInScope(Department::class, keep: $keep('department_id'))],
            'team_id' => ['nullable', 'integer', new ExistsInScope(Team::class, keep: $keep('team_id'))],
            'position_id' => ['nullable', 'integer', new ExistsInScope(Position::class, keep: $keep('position_id'))],
            'job_title_id' => ['nullable', 'integer', new ExistsInScope(JobTitle::class, keep: $keep('job_title_id'))],
            'job_grade_id' => ['nullable', 'integer', new ExistsInScope(JobGrade::class, keep: $keep('job_grade_id'))],
            'employment_type_id' => ['required', 'integer', new ExistsInScope(EmploymentType::class, keep: $keep('employment_type_id'))],
            'cost_center_id' => ['nullable', 'integer', new ExistsInScope(CostCenter::class, keep: $keep('cost_center_id'))],
            'manager_id' => ['nullable', 'integer', new ExistsInScope(Employee::class, keep: $keep('manager_id')), 'not_in:'.($employee?->getKey() ?? 0)],
            'work_location_id' => ['nullable', 'integer', new ExistsInScope(BranchLocation::class, keep: $keep('work_location_id'))],
        ];
    }

    protected function validatePlacement(Validator $validator, ?Employee $employee): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $placement = app(EmployeePlacement::class);
        $data = array_intersect_key($this->all(), array_flip(Employee::PLACEMENT_COLUMNS));

        foreach ($placement->errors($placement->complete($data, $employee), $employee) as $field => $message) {
            $validator->errors()->add($field, $message);
        }
    }

    /**
     * The placement columns of the validated data.
     *
     * @return array<string, mixed>
     */
    public function placement(): array
    {
        $validated = $this->validated();
        $placement = [];

        foreach (Employee::PLACEMENT_COLUMNS as $column) {
            if ($column !== 'status') {
                $placement[$column] = $validated[$column] ?? null;
            }
        }

        return $placement;
    }
}
