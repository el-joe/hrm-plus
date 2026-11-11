<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\User;
use App\Services\Approvals\ApprovalService;

/**
 * Loans and advances in the admin area: listed with loans.view inside the
 * data scope; requested on an employee's behalf with loans.create; decided
 * by whoever the approval engine lets act (never on one's own loan);
 * repayments, schedule-change requests and final settlement with
 * loans.update; pending requests withdrawn with loans.delete.
 */
class EmployeeLoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('loans.view');
    }

    public function view(User $user, EmployeeLoan $loan): bool
    {
        return $user->can('loans.view') && $loan->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('loans.create');
    }

    public function createFor(User $user, Employee $employee): bool
    {
        return $user->can('loans.create') && $employee->isVisibleTo($user) && $user->employee_id !== $employee->id;
    }

    public function export(User $user): bool
    {
        return $user->can('loans.export');
    }

    public function decide(User $user, EmployeeLoan $loan): bool
    {
        $approval = $loan->latestApprovalRequest;

        return $loan->isPending() && $user->employee_id !== $loan->employee_id
            && $approval !== null && app(ApprovalService::class)->canAct($approval, $user);
    }

    public function update(User $user, EmployeeLoan $loan): bool
    {
        return $user->can('loans.update') && $loan->isVisibleTo($user) && $loan->isActive() && $user->employee_id !== $loan->employee_id;
    }

    public function cancel(User $user, EmployeeLoan $loan): bool
    {
        return $user->can('loans.delete') && $loan->isVisibleTo($user) && $loan->isPending();
    }
}
