<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

/**
 * Employee records: a permission AND the record inside the admin's data
 * scope (branch / department incl. sub-departments / employee group).
 * Separate permissions guard the sensitive identity numbers
 * (employee_sensitive.*), the financial data (employee_financials.*) and the
 * documents (employee_documents.*).
 */
class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('employees.view');
    }

    public function view(User $user, Employee $employee): bool
    {
        return $user->can('employees.view') && $employee->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('employees.create');
    }

    /**
     * Personal data, placement changes (transfer, promotion, ...), activation and contracts.
     */
    public function update(User $user, Employee $employee): bool
    {
        return $user->can('employees.update') && $employee->isVisibleTo($user);
    }

    /**
     * Terminate, rehire and the employee's login account.
     */
    public function manage(User $user, Employee $employee): bool
    {
        return $user->can('employees.manage') && $employee->isVisibleTo($user);
    }

    public function export(User $user): bool
    {
        return $user->can('employees.export');
    }

    /**
     * Bulk operations, import.
     */
    public function bulkUpdate(User $user): bool
    {
        return $user->can('employees.update');
    }

    public function import(User $user): bool
    {
        return $user->can('employees.create');
    }

    public function viewSensitive(User $user, Employee $employee): bool
    {
        return $user->can('employee_sensitive.view') && $employee->isVisibleTo($user);
    }

    public function updateSensitive(User $user, Employee $employee): bool
    {
        return $user->can('employee_sensitive.update') && $employee->isVisibleTo($user);
    }

    public function viewFinancial(User $user, Employee $employee): bool
    {
        return $user->can('employee_financials.view') && $employee->isVisibleTo($user);
    }

    public function updateFinancial(User $user, Employee $employee): bool
    {
        return $user->can('employee_financials.update') && $employee->isVisibleTo($user);
    }

    public function viewDocuments(User $user, Employee $employee): bool
    {
        return $user->can('employee_documents.view') && $employee->isVisibleTo($user);
    }

    public function createDocuments(User $user, Employee $employee): bool
    {
        return $user->can('employee_documents.create') && $employee->isVisibleTo($user);
    }

    public function updateDocuments(User $user, Employee $employee): bool
    {
        return $user->can('employee_documents.update') && $employee->isVisibleTo($user);
    }

    public function deleteDocuments(User $user, Employee $employee): bool
    {
        return $user->can('employee_documents.delete') && $employee->isVisibleTo($user);
    }
}
