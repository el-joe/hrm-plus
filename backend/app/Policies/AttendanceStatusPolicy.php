<?php

namespace App\Policies;

use App\Models\AttendanceStatus;
use App\Models\User;

/**
 * Configurable day statuses: listed with attendance.view, maintained with
 * attendance.manage. System statuses (absent) cannot be deleted.
 */
class AttendanceStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.view');
    }

    public function create(User $user): bool
    {
        return $user->can('attendance.manage');
    }

    public function update(User $user, AttendanceStatus $status): bool
    {
        return $user->can('attendance.manage');
    }

    public function delete(User $user, AttendanceStatus $status): bool
    {
        return $user->can('attendance.manage') && ! $status->is_system;
    }
}
