<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeeLoan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * The employee's own loans and advances for the portal and the mobile API:
 * always the signed-in employee's records, admin data scopes ignored.
 */
class PortalLoans
{
    /**
     * @return Builder<EmployeeLoan>
     */
    public function loans(Employee $employee): Builder
    {
        return EmployeeLoan::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->with('latestApprovalRequest.currentStep')->latest('id');
    }

    public function paginate(Employee $employee, int $perPage = 20): LengthAwarePaginator
    {
        return $this->loans($employee)->paginate($perPage)->withQueryString();
    }

    public function find(Employee $employee, int $id): ?EmployeeLoan
    {
        return $this->loans($employee)->with(['instalments', 'transactions', 'approvalRequests.actions'])->find($id);
    }
}
