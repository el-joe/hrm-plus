<?php

namespace App\Actions\Employees;

use App\Enums\AssignmentChangeType;
use App\Enums\EmployeeStatus;
use App\Models\BranchLocation;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Team;
use App\Services\Employees\EmployeePlacement;
use App\Services\Employees\EmploymentHistory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bulk operations from the employee list. Each employee gets its own history
 * row, exactly as if changed one by one; employees the change does not fit
 * (terminated, inconsistent placement, manager cycle) are skipped and
 * reported instead of failing the whole batch.
 */
class BulkUpdateEmployees
{
    public const ACTIONS = ['activate', 'deactivate', 'assign_branch', 'assign_department', 'assign_manager', 'add_to_group', 'remove_from_group'];

    public function __construct(
        private readonly ChangeEmployeeStatus $changeStatus,
        private readonly EmployeePlacement $placement,
        private readonly EmploymentHistory $history,
    ) {}

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  array{branch_id?: int|null, department_id?: int|null, manager_id?: int|null, group_id?: int|null, reason?: string|null}  $data
     * @return array{updated: int, skipped: array<string, string>}
     */
    public function handle(Collection $employees, string $action, array $data): array
    {
        $result = ['updated' => 0, 'skipped' => []];

        DB::transaction(function () use ($employees, $action, $data, &$result): void {
            foreach ($employees as $employee) {
                $outcome = $this->apply($employee, $action, $data);

                if ($outcome === true) {
                    $result['updated']++;
                } elseif (is_string($outcome)) {
                    $result['skipped'][$employee->employee_number] = $outcome;
                }
            }
        });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return bool|string true = changed, false = nothing to change, string = skipped with this reason
     */
    private function apply(Employee $employee, string $action, array $data): bool|string
    {
        if (in_array($action, ['add_to_group', 'remove_from_group'], true)) {
            $attached = $employee->groups()->whereKey($data['group_id'])->exists();

            if ($action === 'add_to_group' && ! $attached) {
                $employee->groups()->attach($data['group_id'], ['created_at' => now()]);

                return true;
            }

            if ($action === 'remove_from_group' && $attached) {
                $employee->groups()->detach($data['group_id']);

                return true;
            }

            return false;
        }

        if ($employee->isTerminated()) {
            return __('employees.bulk.skip_terminated');
        }

        $reason = $data['reason'] ?? null;

        return match ($action) {
            'activate' => $this->changeStatus->handle($employee, EmployeeStatus::Active, $reason) !== null,
            'deactivate' => $this->changeStatus->handle($employee, EmployeeStatus::Inactive, $reason) !== null,
            'assign_branch' => $this->assignBranch($employee, (int) $data['branch_id'], $reason),
            'assign_department' => $this->assignDepartment($employee, Department::query()->withoutGlobalScopes()->findOrFail($data['department_id']), $reason),
            'assign_manager' => $this->assignManager($employee, (int) $data['manager_id'], $reason),
        };
    }

    private function assignBranch(Employee $employee, int $branchId, ?string $reason): bool|string
    {
        if ((int) $employee->branch_id === $branchId) {
            return false;
        }

        $department = Department::query()->withoutGlobalScopes()->find($employee->department_id);

        if ($department?->branch_id !== null) {
            return __('employees.validation.department_not_in_branch');
        }

        $changes = ['branch_id' => $branchId];

        if ($employee->work_location_id !== null && (int) BranchLocation::query()->withoutGlobalScopes()->whereKey($employee->work_location_id)->value('branch_id') !== $branchId) {
            $changes['work_location_id'] = null;
        }

        $this->history->recordAssignment($employee, AssignmentChangeType::Transfer, $changes, today(), $reason);

        return true;
    }

    private function assignDepartment(Employee $employee, Department $department, ?string $reason): bool|string
    {
        if ((int) $employee->department_id === (int) $department->getKey()) {
            return false;
        }

        $changes = [
            'department_id' => $department->getKey(),
            'branch_id' => $department->branch_id ?? $employee->branch_id,
            'cost_center_id' => $department->cost_center_id ?? $employee->cost_center_id,
        ];

        if ($employee->team_id !== null && (int) Team::query()->withoutGlobalScopes()->whereKey($employee->team_id)->value('department_id') !== (int) $department->getKey()) {
            $changes['team_id'] = null;
        }

        if ($employee->position_id !== null && (int) Position::query()->withoutGlobalScopes()->whereKey($employee->position_id)->value('department_id') !== (int) $department->getKey()) {
            $changes['position_id'] = null;
        }

        if ($employee->work_location_id !== null && (int) BranchLocation::query()->withoutGlobalScopes()->whereKey($employee->work_location_id)->value('branch_id') !== (int) $changes['branch_id']) {
            $changes['work_location_id'] = null;
        }

        $this->history->recordAssignment($employee, AssignmentChangeType::Transfer, $changes, today(), $reason);

        return true;
    }

    private function assignManager(Employee $employee, int $managerId, ?string $reason): bool|string
    {
        if ((int) $employee->manager_id === $managerId) {
            return false;
        }

        $errors = $this->placement->errors(['manager_id' => $managerId], $employee, checkCapacity: false);

        if (isset($errors['manager_id'])) {
            return $errors['manager_id'];
        }

        $this->history->recordAssignment($employee, AssignmentChangeType::ManagerChange, ['manager_id' => $managerId], today(), $reason);

        return true;
    }
}
