<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Where an employee sits on a date for payroll purposes: the latest applied
 * placement row on or before the date (so a terminated employee keeps the
 * placement of their last day), and the active employee groups.
 */
class PayrollPlacement
{
    /** @var array<int, int|null>|null */
    private ?array $departmentParents = null;

    public function on(Employee $employee, CarbonInterface|string $date): ?EmployeeAssignment
    {
        $day = is_string($date) ? $date : $date->format('Y-m-d');

        return EmployeeAssignment::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereNotNull('applied_at')->whereNull('cancelled_at')
            ->where('effective_date', '<=', $day)
            ->orderByDesc('effective_date')->orderByDesc('id')
            ->first();
    }

    /**
     * @return list<int>
     */
    public function groupIds(Employee $employee): array
    {
        return DB::table('employee_group_members')
            ->join('employee_groups', 'employee_groups.id', '=', 'employee_group_members.employee_group_id')
            ->where('employee_group_members.employee_id', $employee->id)
            ->where('employee_groups.is_active', true)
            ->pluck('employee_group_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * The department and its ancestors, nearest first.
     *
     * @return list<int>
     */
    public function departmentChain(?int $departmentId): array
    {
        $parents = $this->departmentParents ??= DB::table('departments')->pluck('parent_id', 'id')->map(fn ($id): ?int => $id === null ? null : (int) $id)->all();

        $chain = [];
        $current = $departmentId;

        while ($current !== null && ! in_array($current, $chain, true)) {
            $chain[] = $current;
            $current = $parents[$current] ?? null;
        }

        return $chain;
    }
}
