<?php

namespace App\Actions\Roles;

use App\Models\ApprovalWorkflowStep;
use App\Models\Role;
use Illuminate\Validation\ValidationException;

class DeleteRole
{
    /**
     * @throws ValidationException when the role is a system role, still assigned to admins, or an approver in a workflow
     */
    public function handle(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => __('app.roles.cannot_delete_system')]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => __('app.roles.cannot_delete_assigned')]);
        }

        if (ApprovalWorkflowStep::query()->where('approver_role_id', $role->id)->exists()) {
            throw ValidationException::withMessages(['role' => __('app.roles.cannot_delete_approver')]);
        }

        $role->delete();
    }
}
