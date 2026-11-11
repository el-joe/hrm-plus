<?php

namespace App\Services\Employees;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;

/**
 * Turns the position-based structure of Phase 3a into people:
 *
 * - holderOf(): the employee holding a position (active first, then by hire date);
 * - defaultManagerFor(): the holder of the nearest position up the reporting
 *   line (reports_to_position_id), falling back to the department head and
 *   then the heads of the parent departments;
 * - departmentHead() / branchManager(): holders of head_position_id /
 *   manager_position_id.
 *
 * Queries ignore data scopes: a manager outside the admin's scope is still the manager.
 */
class ManagerResolver
{
    public function holderOf(?Position $position, ?Employee $except = null): ?Employee
    {
        if ($position === null) {
            return null;
        }

        return Employee::query()->withoutGlobalScopes()
            ->where('position_id', $position->getKey())
            ->employed()
            ->when($except?->exists, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->orderByRaw('case when status = ? then 0 else 1 end', ['active'])
            ->orderBy('hire_date')
            ->orderBy('id')
            ->first();
    }

    public function defaultManagerFor(?Position $position, ?Department $department = null, ?Employee $employee = null): ?Employee
    {
        $visited = [];
        $current = $position?->reportsTo()->withoutGlobalScopes()->first();

        while ($current !== null && ! in_array($current->getKey(), $visited, true)) {
            $visited[] = $current->getKey();

            if ($holder = $this->holderOf($current, $employee)) {
                return $holder;
            }

            $current = $current->reportsTo()->withoutGlobalScopes()->first();
        }

        $department ??= $position?->department()->withoutGlobalScopes()->first();
        $seen = [];

        while ($department !== null && ! in_array($department->getKey(), $seen, true)) {
            $seen[] = $department->getKey();
            $headPosition = $department->headPosition()->withoutGlobalScopes()->first();

            // The head of the employee's own department reports further up, not to themselves.
            if ($headPosition !== null && $headPosition->getKey() !== $position?->getKey()) {
                if ($head = $this->holderOf($headPosition, $employee)) {
                    return $head;
                }
            }

            $department = $department->parent()->withoutGlobalScopes()->first();
        }

        return null;
    }

    public function departmentHead(Department $department): ?Employee
    {
        return $this->holderOf($department->headPosition()->withoutGlobalScopes()->first());
    }

    public function branchManager(Branch $branch): ?Employee
    {
        return $this->holderOf($branch->managerPosition()->withoutGlobalScopes()->first());
    }
}
