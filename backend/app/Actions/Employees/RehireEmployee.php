<?php

namespace App\Actions\Employees;

use App\Enums\AssignmentChangeType;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Services\Employees\EmployeePlacement;
use App\Services\Employees\EmploymentHistory;
use Illuminate\Support\Carbon;

/**
 * Brings a terminated employee back under the same employee record and
 * number. Earlier employment periods stay in the history; hire_date becomes
 * the rehire date while original_hire_date keeps the first one.
 */
class RehireEmployee
{
    public function __construct(
        private readonly EmployeePlacement $placement,
        private readonly EmploymentHistory $history,
    ) {}

    /**
     * @param  array<string, mixed>  $placement
     */
    public function handle(Employee $employee, array $placement, string $rehireDate, ?string $reason): EmployeeAssignment
    {
        $placement = $this->placement->complete($placement, $employee);
        $placement['status'] = EmployeeStatus::Active->value;

        return $this->history->recordAssignment($employee, AssignmentChangeType::Rehire, $placement, Carbon::parse($rehireDate), $reason);
    }
}
