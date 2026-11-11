<?php

namespace App\Actions\Employees;

use App\Enums\AssignmentChangeType;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Services\Employees\EmployeePlacement;
use App\Services\Employees\EmploymentHistory;
use Illuminate\Support\Carbon;

/**
 * Transfer, promotion, reassignment or manager change: a new dated
 * placement row (applied now, or on its date when in the future). An empty
 * manager is resolved from the new position's reporting line.
 */
class ChangeEmployeeAssignment
{
    public function __construct(
        private readonly EmployeePlacement $placement,
        private readonly EmploymentHistory $history,
    ) {}

    /**
     * @param  array<string, mixed>  $placement  placement columns from the form
     */
    public function handle(Employee $employee, AssignmentChangeType $type, array $placement, string $effectiveDate, ?string $reason): EmployeeAssignment
    {
        $placement = $this->placement->complete($placement, $employee);
        unset($placement['status']);

        return $this->history->recordAssignment($employee, $type, $placement, Carbon::parse($effectiveDate), $reason);
    }
}
