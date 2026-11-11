<?php

namespace App\Services\Attendance;

use App\Enums\AssignmentChangeType;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The employee's placement on a past or present date, from the effective-dated
 * assignment history. No attendance is expected outside employment: before
 * the hire date, after the termination date (the termination date itself is
 * the last working day) or while the employee is inactive.
 */
class EmploymentTimeline
{
    /** @var array<int, Collection<int, EmployeeAssignment>> */
    private array $history = [];

    /**
     * The applied assignment row in force on the date, or null when the employee is not employed then.
     */
    public function placementOn(Employee $employee, CarbonImmutable $date): ?EmployeeAssignment
    {
        $day = $date->format('Y-m-d');
        $row = $this->rows($employee)->last(fn (EmployeeAssignment $row): bool => $row->effective_date->format('Y-m-d') <= $day);

        if ($row === null) {
            return null;
        }

        if ($row->status === EmployeeStatus::Terminated) {
            return $row->change_type === AssignmentChangeType::Termination && $row->effective_date->format('Y-m-d') === $day ? $row : null;
        }

        return $row->status === EmployeeStatus::Active ? $row : null;
    }

    public function forget(?int $employeeId = null): void
    {
        if ($employeeId === null) {
            $this->history = [];
        } else {
            unset($this->history[$employeeId]);
        }
    }

    /**
     * @return Collection<int, EmployeeAssignment>
     */
    private function rows(Employee $employee): Collection
    {
        return $this->history[$employee->id] ??= EmployeeAssignment::query()
            ->where('employee_id', $employee->id)
            ->whereNotNull('applied_at')->whereNull('cancelled_at')
            ->orderBy('effective_date')->orderBy('id')
            ->get();
    }
}
