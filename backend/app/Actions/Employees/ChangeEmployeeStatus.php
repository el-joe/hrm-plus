<?php

namespace App\Actions\Employees;

use App\Enums\AssignmentChangeType;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Services\Employees\EmploymentHistory;
use InvalidArgumentException;

/**
 * Activates or deactivates (suspends) an employee who is still employed.
 * Terminated employees go through RehireEmployee instead.
 */
class ChangeEmployeeStatus
{
    public function __construct(private readonly EmploymentHistory $history) {}

    public function handle(Employee $employee, EmployeeStatus $status, ?string $reason = null): ?EmployeeAssignment
    {
        if ($status === EmployeeStatus::Terminated || $employee->isTerminated()) {
            throw new InvalidArgumentException('Use TerminateEmployee / RehireEmployee for terminations.');
        }

        if ($employee->status === $status) {
            return null;
        }

        return $this->history->recordAssignment($employee, AssignmentChangeType::StatusChange, ['status' => $status->value], today(), $reason);
    }
}
