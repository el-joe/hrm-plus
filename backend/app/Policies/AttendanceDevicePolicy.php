<?php

namespace App\Policies;

use App\Models\AttendanceDevice;
use App\Models\User;

/**
 * Attendance devices, within the admin's branch scope (HasDataScope).
 */
class AttendanceDevicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance_devices.view');
    }

    public function view(User $user, AttendanceDevice $device): bool
    {
        return $user->can('attendance_devices.view') && $device->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('attendance_devices.create');
    }

    public function update(User $user, AttendanceDevice $device): bool
    {
        return $user->can('attendance_devices.update') && $device->isVisibleTo($user);
    }

    /**
     * Sync now, historical / file import, push token, mappings.
     */
    public function manage(User $user, AttendanceDevice $device): bool
    {
        return $user->can('attendance_devices.manage') && $device->isVisibleTo($user);
    }

    /**
     * Only a device that never delivered an event; otherwise deactivate it.
     */
    public function delete(User $user, AttendanceDevice $device): bool
    {
        return $user->can('attendance_devices.delete') && $device->isVisibleTo($user) && $device->events()->doesntExist();
    }
}
