<?php

namespace App\Services\Attendance\Mobile;

use App\Enums\WorkLocationScope;
use App\Models\AttendanceWorkLocationRule;
use App\Models\BranchLocation;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use Carbon\CarbonImmutable;

/**
 * The locations an employee may use for mobile attendance on a date:
 *
 * 1. rules for the employee (effective on the date), else
 * 2. rules for their department, nearest up the department tree, else
 * 3. the active locations of their branch (the default).
 *
 * A rule either names a location (any branch) or allows anywhere (remote
 * work: no geofence; accuracy, time, shift, mock and face checks still apply).
 */
class WorkLocationResolver
{
    public function for(Employee $employee, ?EmployeeAssignment $placement, CarbonImmutable $date): AllowedLocations
    {
        $day = $date->format('Y-m-d');
        $rules = AttendanceWorkLocationRule::query()->with('location')->effectiveOn($day)
            ->where('scope_type', WorkLocationScope::Employee)->where('scope_id', $employee->id)->get();
        $source = 'employee';

        if ($rules->isEmpty()) {
            $source = 'department';

            foreach ($this->departmentChain((int) ($placement?->department_id ?? $employee->department_id)) as $departmentId) {
                $rules = AttendanceWorkLocationRule::query()->with('location')->effectiveOn($day)
                    ->where('scope_type', WorkLocationScope::Department)->where('scope_id', $departmentId)->get();

                if ($rules->isNotEmpty()) {
                    break;
                }
            }
        }

        if ($rules->isNotEmpty()) {
            return new AllowedLocations(
                $rules->pluck('location')->filter(fn (?BranchLocation $location): bool => $location !== null && $location->is_active)->unique('id')->values(),
                $rules->contains('allow_anywhere', true),
                $source,
            );
        }

        $branchId = $placement?->branch_id ?? $employee->branch_id;

        return new AllowedLocations(
            BranchLocation::query()->withoutGlobalScopes()->where('branch_id', $branchId)->where('is_active', true)->orderBy('id')->get(),
            false,
            'branch',
        );
    }

    /**
     * @return list<int> the department and its ancestors, nearest first
     */
    private function departmentChain(int $departmentId): array
    {
        $chain = [];

        while ($departmentId > 0 && ! in_array($departmentId, $chain, true) && count($chain) < 20) {
            $chain[] = $departmentId;
            $departmentId = (int) Department::query()->withoutGlobalScopes()->whereKey($departmentId)->value('parent_id');
        }

        return $chain;
    }
}
