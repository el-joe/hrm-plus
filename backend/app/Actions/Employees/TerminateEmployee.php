<?php

namespace App\Actions\Employees;

use App\Enums\AssignmentChangeType;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Services\Employees\EmploymentHistory;
use Illuminate\Support\Carbon;

/**
 * Ends employment on a date (today, past or scheduled for the future). The
 * employee keeps every record (history, documents, contracts, salary); the
 * open contract is closed on the same date and their own login account is
 * switched off when the termination takes effect.
 */
class TerminateEmployee
{
    public function __construct(private readonly EmploymentHistory $history) {}

    /**
     * @param  array{termination_date: string, termination_type: string, reason: string, eligible_for_rehire?: bool}  $data
     */
    public function handle(Employee $employee, array $data): EmployeeAssignment
    {
        $date = Carbon::parse($data['termination_date']);

        $assignment = $this->history->recordAssignment(
            $employee,
            AssignmentChangeType::Termination,
            ['status' => EmployeeStatus::Terminated->value],
            $date,
            $data['reason'],
            ['termination_type' => $data['termination_type'], 'eligible_for_rehire' => (bool) ($data['eligible_for_rehire'] ?? false)],
        );

        $employee->contracts()->whereNull('terminated_on')
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>', $date))
            ->whereDate('start_date', '<=', $date)
            ->get()
            ->each(fn ($contract) => $contract->update(['terminated_on' => $date->toDateString()]));

        return $assignment;
    }
}
