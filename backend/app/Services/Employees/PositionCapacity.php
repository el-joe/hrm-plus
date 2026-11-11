<?php

namespace App\Services\Employees;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\Position;

/**
 * Position headcount: the employees holding a position (active or inactive,
 * not terminated) plus scheduled moves into it may never exceed its approved
 * headcount, and the headcount can never be lowered below its holders.
 */
class PositionCapacity
{
    /**
     * Seats taken, ignoring the given employee (who is being placed or already sits there).
     */
    public function occupied(Position $position, ?Employee $except = null): int
    {
        $holders = Employee::query()->withoutGlobalScopes()
            ->where('position_id', $position->getKey())
            ->employed()
            ->when($except?->exists, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->count();

        $incoming = EmployeeAssignment::query()
            ->pending()
            ->where('position_id', $position->getKey())
            ->when($except?->exists, fn ($query) => $query->where('employee_id', '!=', $except->getKey()))
            ->whereHas('employee', fn ($query) => $query->withoutGlobalScopes()
                ->where(fn ($query) => $query->whereNull('position_id')->orWhere('position_id', '!=', $position->getKey())))
            ->distinct()
            ->count('employee_id');

        return $holders + $incoming;
    }

    public function hasRoomFor(Position $position, ?Employee $employee = null): bool
    {
        return $this->occupied($position, $employee) < $position->headcount;
    }

    /**
     * Employees holding the position now (for the headcount check on positions).
     */
    public function holders(Position $position): int
    {
        return $position->holders()->withoutGlobalScopes()->count();
    }
}
