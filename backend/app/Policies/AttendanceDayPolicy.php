<?php

namespace App\Policies;

use App\Models\AttendanceDay;
use App\Models\User;

/**
 * Processed days: a permission AND the day inside the admin's data scope
 * (branch / department the employee had that day, or employee group).
 */
class AttendanceDayPolicy
{
    public function view(User $user, AttendanceDay $day): bool
    {
        return $user->can('attendance.view') && $day->isVisibleTo($user);
    }

    public function correct(User $user, AttendanceDay $day): bool
    {
        return $user->can('attendance.update') && $day->isVisibleTo($user);
    }

    public function decideOvertime(User $user, AttendanceDay $day): bool
    {
        return $user->can('attendance.approve') && $day->isVisibleTo($user) && ! $day->isLocked();
    }

    public function reprocess(User $user, AttendanceDay $day): bool
    {
        return $user->can('attendance.manage') && $day->isVisibleTo($user);
    }
}
