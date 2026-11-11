<?php

namespace App\Actions\Employees;

use App\Enums\SalaryChangeType;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Services\Employees\EmploymentHistory;
use Illuminate\Support\Carbon;

/**
 * A new dated basic salary. The change type (increase / decrease) is derived
 * with bcmath unless the caller names it (promotion, adjustment).
 */
class ChangeEmployeeSalary
{
    public function __construct(private readonly EmploymentHistory $history) {}

    public function handle(Employee $employee, string $amount, string $effectiveDate, ?string $reason, ?SalaryChangeType $type = null): EmployeeSalary
    {
        $type ??= match (true) {
            $employee->basic_salary === null => SalaryChangeType::Initial,
            bccomp($amount, (string) $employee->basic_salary, 2) < 0 => SalaryChangeType::Decrease,
            default => SalaryChangeType::Increase,
        };

        return $this->history->recordSalary($employee, $amount, $type, Carbon::parse($effectiveDate), $reason);
    }
}
