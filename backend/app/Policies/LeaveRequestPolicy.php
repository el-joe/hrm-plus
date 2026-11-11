<?php

namespace App\Policies;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

/**
 * Leave requests in the admin area: listed with leaves.view inside the data
 * scope; filed on an employee's behalf with leaves.create; decided by
 * whoever the approval engine lets act on the current step (self-approval
 * blocked there, and nobody decides their own leave); cancelled directly by admins holding leaves.manage —
 * never their own leave (that goes through the portal like anyone's).
 */
class LeaveRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leaves.view');
    }

    public function view(User $user, LeaveRequest $request): bool
    {
        return $user->can('leaves.view') && $request->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('leaves.create');
    }

    public function createFor(User $user, Employee $employee): bool
    {
        return $user->can('leaves.create') && $employee->isVisibleTo($user);
    }

    public function decide(User $user, LeaveRequest $request): bool
    {
        $approval = $request->latestApprovalRequest;

        return in_array($request->status, [LeaveRequestStatus::Pending, LeaveRequestStatus::CancellationPending], true)
            && $user->employee_id !== $request->employee_id
            && $approval !== null && app(ApprovalService::class)->canAct($approval, $user);
    }

    public function cancel(User $user, LeaveRequest $request): bool
    {
        return $user->can('leaves.manage') && $request->isVisibleTo($user) && $user->employee_id !== $request->employee_id
            && in_array($request->status, [LeaveRequestStatus::Pending, LeaveRequestStatus::Approved], true);
    }
}
