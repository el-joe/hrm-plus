<?php

namespace App\Services\Employees;

use App\Models\BranchLocation;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Team;

/**
 * Business rules of a job placement (branch, department, team, position,
 * title, grade, manager, cost centre, work location), shared by the forms,
 * the bulk actions and the import:
 *
 * - complete(): fills the defaults — job title and grade from the position,
 *   cost centre from the department, direct manager from the position's
 *   reporting line (ManagerResolver);
 * - errors(): consistency checks — the department belongs to the branch (or
 *   is company-wide), the team and position belong to the department, the
 *   work location to the branch, the position has a free seat (headcount),
 *   and the manager is not the employee or someone reporting to them.
 */
class EmployeePlacement
{
    public function __construct(
        private readonly ManagerResolver $managers,
        private readonly PositionCapacity $capacity,
    ) {}

    /**
     * @param  array<string, mixed>  $placement
     * @return array<string, mixed>
     */
    public function complete(array $placement, ?Employee $employee = null): array
    {
        $position = filled($placement['position_id'] ?? null) ? Position::query()->withoutGlobalScopes()->find($placement['position_id']) : null;
        $department = filled($placement['department_id'] ?? null) ? Department::query()->withoutGlobalScopes()->find($placement['department_id']) : null;

        if ($position !== null) {
            $placement['job_title_id'] = filled($placement['job_title_id'] ?? null) ? $placement['job_title_id'] : $position->job_title_id;
            $placement['job_grade_id'] = filled($placement['job_grade_id'] ?? null) ? $placement['job_grade_id'] : $position->job_grade_id;
        }

        if ($department !== null && blank($placement['cost_center_id'] ?? null)) {
            $placement['cost_center_id'] = $department->cost_center_id;
        }

        if (array_key_exists('manager_id', $placement) && blank($placement['manager_id'])) {
            $placement['manager_id'] = $this->managers->defaultManagerFor($position, $department, $employee)?->getKey();
        }

        foreach (Employee::PLACEMENT_COLUMNS as $column) {
            if (array_key_exists($column, $placement) && $placement[$column] === '') {
                $placement[$column] = null;
            }
        }

        return $placement;
    }

    /**
     * @param  array<string, mixed>  $placement
     * @return array<string, string> field => translated message
     */
    public function errors(array $placement, ?Employee $employee = null, bool $checkCapacity = true): array
    {
        $errors = [];
        $department = filled($placement['department_id'] ?? null) ? Department::query()->withoutGlobalScopes()->find($placement['department_id']) : null;

        if ($department !== null && $department->branch_id !== null && (int) $department->branch_id !== (int) ($placement['branch_id'] ?? 0)) {
            $errors['department_id'] = __('employees.validation.department_not_in_branch');
        }

        if (filled($placement['team_id'] ?? null)) {
            $team = Team::query()->withoutGlobalScopes()->find($placement['team_id']);

            if ($team !== null && (int) $team->department_id !== (int) ($placement['department_id'] ?? 0)) {
                $errors['team_id'] = __('employees.validation.team_not_in_department');
            }
        }

        if (filled($placement['work_location_id'] ?? null)) {
            $location = BranchLocation::query()->withoutGlobalScopes()->find($placement['work_location_id']);

            if ($location !== null && (int) $location->branch_id !== (int) ($placement['branch_id'] ?? 0)) {
                $errors['work_location_id'] = __('employees.validation.location_not_in_branch');
            }
        }

        if (filled($placement['position_id'] ?? null)) {
            $position = Position::query()->withoutGlobalScopes()->find($placement['position_id']);

            if ($position !== null && (int) $position->department_id !== (int) ($placement['department_id'] ?? 0)) {
                $errors['position_id'] = __('employees.validation.position_not_in_department');
            } elseif ($position !== null && $checkCapacity && (int) $employee?->position_id !== (int) $position->getKey()
                && ! $this->capacity->hasRoomFor($position, $employee)) {
                $errors['position_id'] = __('employees.validation.position_full', [
                    'position' => $position->name, 'headcount' => $position->headcount,
                ]);
            }
        }

        if (filled($placement['manager_id'] ?? null) && $employee?->exists) {
            if ($this->reportsTo((int) $placement['manager_id'], $employee)) {
                $errors['manager_id'] = __('employees.validation.manager_cycle');
            }
        }

        return $errors;
    }

    /**
     * Whether $managerId is the employee or somewhere below them in the manager chain.
     */
    private function reportsTo(int $managerId, Employee $employee): bool
    {
        $seen = [];
        $current = $managerId;

        while ($current !== null && ! in_array($current, $seen, true)) {
            if ($current === (int) $employee->getKey()) {
                return true;
            }

            $seen[] = $current;
            $current = Employee::query()->withoutGlobalScopes()->whereKey($current)->value('manager_id');
            $current = $current === null ? null : (int) $current;
        }

        return false;
    }
}
