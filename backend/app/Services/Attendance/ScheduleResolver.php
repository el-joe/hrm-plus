<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceScope;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleOverride;
use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Services\Attendance\Data\ResolvedSchedule;
use App\Services\Attendance\Data\ScheduledShift;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * ScheduleResolver::for($employee, $date): the expected shift(s) of an
 * employee on a local date. The single source used by the attendance engine,
 * the calendar, the portal / API, mobile check-in validation (Phase 5b) and
 * payroll.
 *
 * Resolution order:
 * 1. not employed on the date (EmploymentTimeline) -> nothing expected;
 * 2. date-specific overrides (schedule_overrides: manual, approved change or swap);
 * 3. the schedule assigned with the highest precedence, effective on the date:
 *    employee > employee group > department (nearest up the tree) > branch > company;
 *    within one level the latest effective_from (then the newest row) wins;
 * 4. no schedule: the company weekend rule (setting attendance.working_days),
 *    i.e. rest on non-working days, no fixed shift on working days.
 *
 * Holidays never remove the shifts: the engine decides what working on a
 * holiday means. Instances cache reference data; resolve a fresh instance
 * per request or job.
 */
class ScheduleResolver
{
    /** @var Collection<int, ScheduleAssignment>|null */
    private ?Collection $assignments = null;

    /** @var array<int, WorkSchedule> */
    private array $schedules = [];

    /** @var array<int, Shift> */
    private array $shifts = [];

    /** @var array<int, list<int>> */
    private array $groups = [];

    /** @var array<int, ?int>|null */
    private ?array $departmentParents = null;

    /**
     * Loaded override ranges and rows: "employee|Y-m-d" => overrides.
     *
     * @var array<string, list<ScheduleOverride>>
     */
    private array $overrides = [];

    /** @var array<int, array{0: string, 1: string}> */
    private array $overrideRanges = [];

    public function __construct(
        private readonly EmploymentTimeline $timeline,
        private readonly HolidayCalendar $holidays,
        private readonly AttendanceClock $clock,
        private readonly SettingsService $settings,
    ) {}

    public function for(Employee $employee, DateTimeInterface|string $date): ResolvedSchedule
    {
        $date = AttendanceClock::date($date);
        $placement = $this->timeline->placementOn($employee, $date);

        if ($placement === null) {
            return new ResolvedSchedule($employee->id, $date, $this->clock->timezoneForBranch($employee->branch_id), null, [], false, null, 'not_employed');
        }

        $timezone = $this->clock->timezoneForBranch($placement->branch_id);
        $holiday = $this->holidays->holidayOn($date, $placement->branch_id);
        $overrides = $this->overridesFor($employee->id, $date);

        if ($overrides !== []) {
            $shiftIds = array_values(array_filter(array_map(fn (ScheduleOverride $override): ?int => $override->shift_id, $overrides)));

            return new ResolvedSchedule($employee->id, $date, $timezone, $placement, $this->buildShifts($shiftIds, $date, $timezone), $shiftIds === [], $holiday, 'override');
        }

        [$assignment, $scope] = $this->assignmentFor($employee, $placement->branch_id, $placement->department_id, $date);
        $schedule = $assignment ? $this->schedule($assignment->work_schedule_id) : null;
        $dayIndex = $schedule?->dayIndexFor($date);

        if ($schedule !== null && $dayIndex !== null) {
            $shiftIds = $schedule->pattern()->get($dayIndex, []);

            return new ResolvedSchedule(
                $employee->id, $date, $timezone, $placement, $this->buildShifts($shiftIds, $date, $timezone), $shiftIds === [],
                $holiday, $scope->value, $schedule->id, $assignment->id,
            );
        }

        $workingDays = (array) $this->settings->get('attendance.working_days');
        $isRest = ! in_array(strtolower($date->englishDayOfWeek), $workingDays, true);

        return new ResolvedSchedule($employee->id, $date, $timezone, $placement, [], $isRest, $holiday, 'weekend_rule');
    }

    /**
     * Resolve every date of a range (inclusive), keyed by Y-m-d.
     *
     * @return array<string, ResolvedSchedule>
     */
    public function forRange(Employee $employee, DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        $from = AttendanceClock::date($from);
        $to = AttendanceClock::date($to);
        $this->preloadOverrides([$employee->id], $from, $to);
        $days = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $days[$date->format('Y-m-d')] = $this->for($employee, $date);
        }

        return $days;
    }

    /**
     * Load the overrides of many employees at once (calendar pages).
     *
     * @param  list<int>  $employeeIds
     */
    public function preloadOverrides(array $employeeIds, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $rows = ScheduleOverride::query()->withoutGlobalScopes()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('work_date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderBy('id')->get();

        foreach ($employeeIds as $employeeId) {
            $this->overrideRanges[$employeeId] = [$from->format('Y-m-d'), $to->format('Y-m-d')];
        }

        foreach ($rows as $row) {
            $this->overrides[$row->employee_id.'|'.$row->work_date->format('Y-m-d')][] = $row;
        }
    }

    /**
     * Expected shifts built for a date (also used for overrides and previews).
     *
     * @param  list<int>  $shiftIds
     * @return list<ScheduledShift>
     */
    public function buildShifts(array $shiftIds, CarbonImmutable $date, string $timezone): array
    {
        $built = [];

        foreach (array_unique($shiftIds) as $shiftId) {
            $shift = $this->shift($shiftId);

            if ($shift !== null) {
                $built[] = $this->scheduledShift($shift, $date, $timezone);
            }
        }

        usort($built, fn (ScheduledShift $a, ScheduledShift $b): int => $a->startAt <=> $b->startAt);

        return $built;
    }

    public function scheduledShift(Shift $shift, CarbonImmutable $date, string $timezone): ScheduledShift
    {
        $start = AttendanceClock::instant($date, (string) $shift->start_time, $timezone);
        $end = $start->addMinutes($shift->spanMinutes());
        $startMinute = Shift::minuteOfDay($shift->start_time);
        $coreStart = $coreEnd = null;

        if ($shift->isFlexible() && $shift->core_start_time && $shift->core_end_time) {
            $coreStart = $start->addMinutes((Shift::minuteOfDay($shift->core_start_time) - $startMinute + 1440) % 1440);
            $coreEnd = $start->addMinutes((Shift::minuteOfDay($shift->core_end_time) - $startMinute + 1440) % 1440);

            if ($coreEnd->lte($coreStart)) {
                $coreEnd = $coreEnd->addDay();
            }
        }

        return new ScheduledShift(
            $shift,
            $start,
            $end,
            $start->subMinutes((int) $shift->checkin_opens_before_minutes),
            $end->addMinutes((int) $shift->checkout_closes_after_minutes),
            $coreStart,
            $coreEnd,
            $shift->expectedMinutes(),
        );
    }

    /**
     * @return list<ScheduleOverride>
     */
    private function overridesFor(int $employeeId, CarbonImmutable $date): array
    {
        $day = $date->format('Y-m-d');
        $range = $this->overrideRanges[$employeeId] ?? null;

        if ($range === null || $day < $range[0] || $day > $range[1]) {
            $this->preloadOverrides([$employeeId], $date->subDays(1), $date->addDays(1));
        }

        return $this->overrides[$employeeId.'|'.$day] ?? [];
    }

    /**
     * @return array{0: ?ScheduleAssignment, 1: ?AttendanceScope}
     */
    private function assignmentFor(Employee $employee, int $branchId, int $departmentId, CarbonImmutable $date): array
    {
        $day = $date->format('Y-m-d');
        $effective = $this->assignments()->filter(fn (ScheduleAssignment $row): bool => $row->effective_from->format('Y-m-d') <= $day
            && ($row->effective_to === null || $row->effective_to->format('Y-m-d') >= $day));

        $candidates = [
            [AttendanceScope::Employee, [$employee->id]],
            [AttendanceScope::EmployeeGroup, $this->groupIds($employee->id)],
        ];

        foreach ($this->departmentChain($departmentId) as $ancestor) {
            $candidates[] = [AttendanceScope::Department, [$ancestor]];
        }

        $candidates[] = [AttendanceScope::Branch, [$branchId]];
        $candidates[] = [AttendanceScope::Company, [null]];

        foreach ($candidates as [$scope, $ids]) {
            $match = $effective
                ->filter(fn (ScheduleAssignment $row): bool => $row->scope_type === $scope && in_array($row->scope_id, $ids, true))
                ->sortBy([['effective_from', 'desc'], ['id', 'desc']])
                ->first();

            if ($match !== null) {
                return [$match, $scope];
            }
        }

        return [null, null];
    }

    /**
     * @return Collection<int, ScheduleAssignment>
     */
    private function assignments(): Collection
    {
        return $this->assignments ??= ScheduleAssignment::query()
            ->whereHas('schedule', fn ($query) => $query->where('is_active', true))
            ->get();
    }

    private function schedule(int $id): ?WorkSchedule
    {
        return $this->schedules[$id] ??= WorkSchedule::query()->with('days')->find($id);
    }

    private function shift(int $id): ?Shift
    {
        return $this->shifts[$id] ??= Shift::query()->with('breaks')->find($id);
    }

    /**
     * @return list<int>
     */
    private function groupIds(int $employeeId): array
    {
        return $this->groups[$employeeId] ??= DB::table('employee_group_members')
            ->join('employee_groups', 'employee_groups.id', '=', 'employee_group_members.employee_group_id')
            ->where('employee_group_members.employee_id', $employeeId)
            ->where('employee_groups.is_active', true)
            ->pluck('employee_group_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * The department and its ancestors, nearest first.
     *
     * @return list<int>
     */
    private function departmentChain(int $departmentId): array
    {
        $this->departmentParents ??= Department::query()->withoutGlobalScopes()->pluck('parent_id', 'id')
            ->map(fn ($id): ?int => $id === null ? null : (int) $id)->all();

        $chain = [];
        $current = $departmentId;

        while ($current !== null && ! in_array($current, $chain, true)) {
            $chain[] = $current;
            $current = $this->departmentParents[$current] ?? null;
        }

        return $chain;
    }
}
