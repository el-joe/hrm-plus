<?php

namespace App\Policies;

use App\Models\Concerns\HasDataScope;
use App\Models\ScheduleAssignment;
use App\Models\User;

/**
 * Schedule assignments: schedules.* permissions; an assignment that targets
 * an employee, group, department or branch is only editable by admins who can
 * see that record (company-wide ones by anyone holding the permission).
 */
class ScheduleAssignmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('schedules.view');
    }

    public function create(User $user): bool
    {
        return $user->can('schedules.create');
    }

    public function update(User $user, ScheduleAssignment $assignment): bool
    {
        return $user->can('schedules.update') && $this->inScope($user, $assignment);
    }

    public function delete(User $user, ScheduleAssignment $assignment): bool
    {
        return $user->can('schedules.delete') && $this->inScope($user, $assignment);
    }

    private function inScope(User $user, ScheduleAssignment $assignment): bool
    {
        $record = $assignment->scopeRecord();

        return $record === null || ! in_array(HasDataScope::class, class_uses_recursive($record), true) || $record->isVisibleTo($user);
    }
}
