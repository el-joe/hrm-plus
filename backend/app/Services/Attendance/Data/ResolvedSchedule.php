<?php

namespace App\Services\Attendance\Data;

use App\Models\EmployeeAssignment;
use App\Models\Holiday;
use Carbon\CarbonImmutable;

/**
 * What an employee is expected to do on a date: the single answer used by the
 * engine, the portal, the calendar, mobile check-in validation and payroll.
 *
 * source: override | employee | employee_group | department | branch |
 *         company | weekend_rule | not_employed
 */
final readonly class ResolvedSchedule
{
    /**
     * @param  list<ScheduledShift>  $shifts  ordered by start
     */
    public function __construct(
        public int $employeeId,
        public CarbonImmutable $date,
        public string $timezone,
        public ?EmployeeAssignment $placement,
        public array $shifts,
        public bool $isRestDay,
        public ?Holiday $holiday,
        public string $source,
        public ?int $scheduleId = null,
        public ?int $assignmentId = null,
    ) {}

    public function isEmployed(): bool
    {
        return $this->placement !== null;
    }

    public function isWorkingDay(): bool
    {
        return $this->isEmployed() && ! $this->isRestDay && $this->holiday === null && $this->shifts !== [];
    }

    public function branchId(): ?int
    {
        return $this->placement?->branch_id;
    }

    public function departmentId(): ?int
    {
        return $this->placement?->department_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'timezone' => $this->timezone,
            'employed' => $this->isEmployed(),
            'is_rest_day' => $this->isRestDay,
            'holiday_id' => $this->holiday?->id,
            'source' => $this->source,
            'work_schedule_id' => $this->scheduleId,
            'schedule_assignment_id' => $this->assignmentId,
            'shifts' => array_map(fn (ScheduledShift $shift): array => $shift->toArray($this->timezone), $this->shifts),
        ];
    }
}
