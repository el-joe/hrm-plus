<?php

namespace App\Services\Attendance;

use App\Enums\OvertimeStatus;
use App\Enums\PunchDirection;
use App\Enums\RawEventKind;
use App\Enums\RawEventStatus;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceDay;
use App\Models\AttendancePeriodLock;
use App\Models\AttendancePunch;
use App\Models\AttendanceRawEvent;
use App\Models\AttendanceStatus;
use App\Models\Employee;
use App\Services\Attendance\Contracts\LeaveProvider;
use App\Services\Attendance\Data\DayContext;
use App\Services\Attendance\Data\PunchInput;
use App\Services\Attendance\Data\ResolvedSchedule;
use App\Services\Attendance\Data\ScheduledShift;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The one attendance engine. Reads raw events of every source (device,
 * mobile_gps, manual, import), the schedule (ScheduleResolver), the policy
 * version (AttendancePolicyResolver), holidays and approved leave
 * (LeaveProvider) and writes attendance_days.
 *
 * process($employee, $date) is idempotent and deterministic:
 * - punch attribution depends only on the punch and the schedules of its
 *   local date and the dates around it, never on processing order;
 * - the result is hashed; an unchanged result writes nothing;
 * - a day inside a locked period is never overwritten: the difference to its
 *   locked figures (plus earlier adjustments) becomes an attendance_adjustments row.
 *
 * Callers normally go through AttendanceRecalculation (queue or now).
 */
class AttendanceEngine
{
    public const VERSION = 1;

    public function __construct(
        private readonly ScheduleResolver $schedules,
        private readonly AttendancePolicyResolver $policies,
        private readonly DayCalculator $calculator,
        private readonly LeaveProvider $leave,
    ) {}

    /**
     * Process several dates of one employee.
     *
     * @param  iterable<DateTimeInterface|string>  $dates
     * @return list<AttendanceDay>
     */
    public function processDates(Employee $employee, iterable $dates): array
    {
        $days = [];

        foreach ($dates as $date) {
            array_push($days, ...$this->process($employee, $date));
        }

        return $days;
    }

    /**
     * @return list<AttendanceDay>
     */
    public function processRange(Employee $employee, DateTimeInterface|string $from, DateTimeInterface|string $to): array
    {
        $dates = [];

        for ($date = AttendanceClock::date($from); $date->lte(AttendanceClock::date($to)); $date = $date->addDay()) {
            $dates[] = $date;
        }

        return $this->processDates($employee, $dates);
    }

    /**
     * (Re)calculate one employee's date. Returns the day rows as they now stand.
     *
     * @return list<AttendanceDay>
     */
    public function process(Employee $employee, DateTimeInterface|string $date): array
    {
        $date = AttendanceClock::date($date);

        return DB::transaction(function () use ($employee, $date): array {
            // Serialise processing per employee (device push, queue and manual entry may overlap).
            Employee::query()->withoutGlobalScopes()->whereKey($employee->id)->lockForUpdate()->first();

            $resolved = $this->schedules->for($employee, $date);
            $existing = AttendanceDay::query()->withoutGlobalScopes()
                ->where('employee_id', $employee->id)->whereDate('work_date', $date->format('Y-m-d'))
                ->get()->keyBy('shift_key');

            if (! $resolved->isEmployed()) {
                $this->removeStale($existing, [], $date, $resolved);
                $this->markProcessed($employee->id, $date);

                return [];
            }

            $rules = $this->policies->for($employee, $resolved->placement, $date);
            $leave = $this->leave->leaveOn($employee, $date);
            [$attributed, $duplicates] = $this->attributedPunches($employee, $date, $resolved, (int) $rules->punch_dedupe_seconds);
            $marks = $this->marks($employee->id, $date);
            $lock = AttendancePeriodLock::query()->covering($date->format('Y-m-d'), $resolved->branchId())->orderBy('id')->first();

            $targets = $resolved->shifts !== [] ? $resolved->shifts : [null];
            $saved = [];

            foreach ($targets as $shift) {
                $key = $shift?->key() ?? 0;
                [$mark, $markEvent] = $this->markFor($marks, $shift?->key());

                $context = new DayContext(
                    $date, $resolved->timezone, $shift, $resolved->isRestDay, $resolved->holiday, $leave,
                    $mark, $markEvent?->id, $markEvent?->source, $attributed[$key] ?? [], $rules, $resolved->source,
                );

                $result = $this->calculator->calculate($context);
                $attributes = $this->attributes($employee, $resolved, $shift, $context, $result, $duplicates[$key] ?? []);
                $day = $existing->get($key);

                if ($day?->isLocked() || ($day !== null && $lock !== null)) {
                    $saved[] = $this->adjustLocked($day, $attributes, $lock);
                } else {
                    $saved[] = $this->store($day, $attributes, $lock);
                }

                $this->linkPunches($saved[array_key_last($saved)], $attributed[$key] ?? [], $duplicates[$key] ?? []);
            }

            $this->removeStale($existing, array_map(fn (?ScheduledShift $shift): int => $shift?->key() ?? 0, $targets), $date, $resolved);
            $this->markProcessed($employee->id, $date);

            return $saved;
        });
    }

    /**
     * Punches of the employee attributed to this date, grouped by shift key,
     * duplicates (inside the dedupe window) separated out.
     *
     * @return array{0: array<int, list<PunchInput>>, 1: array<int, list<int>>}
     */
    private function attributedPunches(Employee $employee, CarbonImmutable $date, ResolvedSchedule $resolved, int $dedupeSeconds): array
    {
        $timezone = $resolved->timezone;
        $from = CarbonImmutable::parse($date->subDays(1)->format('Y-m-d'), $timezone)->utc();
        $to = CarbonImmutable::parse($date->addDays(2)->format('Y-m-d'), $timezone)->utc();

        $punches = AttendancePunch::query()
            ->join('attendance_raw_events', 'attendance_raw_events.id', '=', 'attendance_punches.attendance_raw_event_id')
            ->where('attendance_punches.employee_id', $employee->id)
            ->whereNull('attendance_raw_events.voided_at')
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->whereNull('attendance_raw_events.work_date')
                    ->where('attendance_punches.punched_at', '>=', $from)->where('attendance_punches.punched_at', '<', $to))
                ->orWhereDate('attendance_raw_events.work_date', $date->format('Y-m-d')))
            ->orderBy('attendance_punches.punched_at')->orderBy('attendance_punches.id')
            ->get(['attendance_punches.*', 'attendance_raw_events.work_date as pinned_date', 'attendance_raw_events.shift_id as pinned_shift_id']);

        $byKey = [];
        $duplicates = [];
        $last = [];

        foreach ($punches as $punch) {
            $at = CarbonImmutable::instance($punch->punched_at)->utc();
            [$attributedDate, $shiftKey] = $this->attribute($employee, $at, $timezone, $punch->pinned_date, $punch->pinned_shift_id === null ? null : (int) $punch->pinned_shift_id);

            if ($attributedDate !== $date->format('Y-m-d')) {
                continue;
            }

            $input = new PunchInput($punch->id, $punch->attendance_raw_event_id, $at, $punch->direction, $punch->source);
            $previous = $last[$shiftKey] ?? null;

            if ($previous !== null && $at->getTimestamp() - $previous->at->getTimestamp() <= $dedupeSeconds
                && ($previous->direction === $input->direction || $input->direction === PunchDirection::Unknown)) {
                $duplicates[$shiftKey][] = $punch->id;

                continue;
            }

            $byKey[$shiftKey][] = $input;
            $last[$shiftKey] = $input;
        }

        return [$byKey, $duplicates];
    }

    /**
     * The (date, shift key) a punch belongs to. A pinned punch (manual entry
     * for a date) goes to that date; otherwise the shift of the local date or
     * its neighbours whose check-in / check-out window contains it and whose
     * span is nearest; otherwise the nearest shift of its local date, or the
     * local date without a shift.
     *
     * @return array{0: string, 1: int}
     */
    private function attribute(Employee $employee, CarbonImmutable $at, string $timezone, ?string $pinnedDate, ?int $pinnedShift): array
    {
        if ($pinnedDate !== null) {
            $pinned = AttendanceClock::date($pinnedDate);
            $shifts = $this->schedules->for($employee, $pinned)->shifts;

            return [$pinned->format('Y-m-d'), $this->nearest($shifts, $at, $pinnedShift)];
        }

        $local = AttendanceClock::localDate($at, $timezone);
        $best = null;

        foreach ([$local->subDay(), $local, $local->addDay()] as $candidateDate) {
            foreach ($this->schedules->for($employee, $candidateDate)->shifts as $shift) {
                if (! $shift->windowContains($at)) {
                    continue;
                }

                $distance = $shift->distanceTo($at);

                if ($best === null || $distance < $best[2]) {
                    $best = [$candidateDate->format('Y-m-d'), $shift->key(), $distance];
                }
            }
        }

        if ($best !== null) {
            return [$best[0], $best[1]];
        }

        return [$local->format('Y-m-d'), $this->nearest($this->schedules->for($employee, $local)->shifts, $at, null)];
    }

    /**
     * @param  list<ScheduledShift>  $shifts
     */
    private function nearest(array $shifts, CarbonImmutable $at, ?int $preferred): int
    {
        if ($shifts === []) {
            return 0;
        }

        foreach ($shifts as $shift) {
            if ($shift->key() === $preferred) {
                return $preferred;
            }
        }

        usort($shifts, fn (ScheduledShift $a, ScheduledShift $b): int => [$a->distanceTo($at), $a->startAt] <=> [$b->distanceTo($at), $b->startAt]);

        return $shifts[0]->key();
    }

    /**
     * Day marks of the date, oldest first.
     *
     * @return Collection<int, AttendanceRawEvent>
     */
    private function marks(int $employeeId, CarbonImmutable $date): Collection
    {
        return AttendanceRawEvent::query()->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('kind', RawEventKind::DayMark)
            ->whereDate('work_date', $date->format('Y-m-d'))
            ->notVoided()
            ->orderBy('id')
            ->get();
    }

    /**
     * The latest mark for the shift (or for the whole day); a mark without a status clears earlier ones.
     *
     * @param  Collection<int, AttendanceRawEvent>  $marks
     * @return array{0: ?AttendanceStatus, 1: ?AttendanceRawEvent}
     */
    private function markFor(Collection $marks, ?int $shiftKey): array
    {
        $mark = $marks->last(fn (AttendanceRawEvent $event): bool => $event->shift_id === null || $event->shift_id === $shiftKey);

        if ($mark === null || $mark->attendance_status_id === null) {
            return [null, null];
        }

        return [AttendanceStatus::query()->find($mark->attendance_status_id), $mark];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<int>  $duplicates
     * @return array<string, mixed>
     */
    private function attributes(Employee $employee, ResolvedSchedule $resolved, ?ScheduledShift $shift, DayContext $context, array $result, array $duplicates): array
    {
        $rules = $context->rules;

        $calculation = [
            'engine_version' => self::VERSION,
            'date' => $resolved->date->format('Y-m-d'),
            'timezone' => $resolved->timezone,
            'schedule' => [
                'source' => $resolved->source,
                'work_schedule_id' => $resolved->scheduleId,
                'schedule_assignment_id' => $resolved->assignmentId,
                'is_rest_day' => $resolved->isRestDay,
                'shift' => $shift?->toArray($resolved->timezone),
            ],
            'holiday' => $resolved->holiday ? ['id' => $resolved->holiday->id, 'name' => $resolved->holiday->name_en, 'is_paid' => $resolved->holiday->is_paid] : null,
            'leave' => $context->leave ? ['reference' => $context->leave->reference, 'type' => $context->leave->typeCode, 'half_day' => $context->leave->halfDay] : null,
            'mark' => $context->mark ? ['raw_event_id' => $context->markEventId, 'status' => $context->mark->code, 'category' => $context->mark->category->value] : null,
            'policy' => [
                'attendance_policy_id' => $rules->attendance_policy_id,
                'attendance_policy_version_id' => $rules->id,
                'version' => $rules->version,
                'rules' => $rules->rules(),
            ],
            'punches' => array_map(fn (PunchInput $punch): array => $punch->toArray(), $context->punches),
            'duplicate_punch_ids' => $duplicates,
            'pairs' => $result['pairs'],
            'ignored_punch_ids' => $result['ignored_punch_ids'],
            'steps' => $result['steps'],
        ];

        $values = [
            'employee_id' => $employee->id,
            'work_date' => $resolved->date->format('Y-m-d'),
            'shift_id' => $shift?->key(),
            'shift_key' => $shift?->key() ?? 0,
            'branch_id' => $resolved->branchId(),
            'department_id' => $resolved->departmentId(),
            'timezone' => $resolved->timezone,
            'status' => $result['status'],
            'attendance_status_id' => $result['attendance_status_id'],
            'holiday_id' => $resolved->holiday?->id,
            'is_rest_day' => $resolved->isRestDay,
            'leave_reference' => $context->leave?->reference,
            'scheduled_start_at' => $shift?->startAt,
            'scheduled_end_at' => $shift?->endAt,
            'scheduled_minutes' => $result['scheduled_minutes'],
            'first_in_at' => $result['first_in_at'],
            'last_out_at' => $result['last_out_at'],
            'worked_minutes' => $result['worked_minutes'],
            'late_minutes' => $result['late_minutes'],
            'early_leave_minutes' => $result['early_leave_minutes'],
            'shortfall_minutes' => $result['shortfall_minutes'],
            'break_minutes' => $result['break_minutes'],
            'overtime_minutes' => $result['overtime_minutes'],
            'overtime_status' => $result['overtime_status'],
            'flags' => $result['flags'],
            'sources' => $result['sources'],
            'is_manual' => $result['is_manual'],
            'attendance_policy_version_id' => $rules->id,
        ];

        $calculation['result'] = array_map(
            fn ($value) => $value instanceof DateTimeInterface ? CarbonImmutable::instance($value)->utc()->toIso8601ZuluString() : ($value instanceof BackedEnum ? $value->value : $value),
            array_diff_key($values, array_flip(['employee_id'])),
        );

        $values['calculation'] = $calculation;
        $values['calculation_hash'] = hash('sha256', json_encode($calculation, JSON_THROW_ON_ERROR));

        return $values;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function store(?AttendanceDay $day, array $attributes, ?AttendancePeriodLock $lock): AttendanceDay
    {
        $day ??= new AttendanceDay;

        if ($day->exists && $day->calculation_hash === $attributes['calculation_hash']) {
            return $day;
        }

        // An overtime decision survives recalculation while the overtime itself is unchanged.
        $keepDecision = $day->exists
            && in_array($day->overtime_status, [OvertimeStatus::Approved, OvertimeStatus::Rejected], true)
            && $attributes['overtime_status'] === OvertimeStatus::Pending
            && $day->overtime_minutes === $attributes['overtime_minutes'];

        $day->fill($attributes);
        $day->overtime_status = $keepDecision ? $day->getOriginal('overtime_status') : $attributes['overtime_status'];
        $day->overtime_approved_minutes = match ($day->overtime_status) {
            OvertimeStatus::Approved => $keepDecision ? $day->getOriginal('overtime_approved_minutes') : $attributes['overtime_minutes'],
            OvertimeStatus::Rejected => 0,
            default => null,
        };
        $day->calculated_at = now();

        if (! $day->exists && $lock !== null) {
            $day->attendance_period_lock_id = $lock->id;
        }

        $day->save();

        return $day;
    }

    /**
     * Locked day: record the difference as an adjustment, never touch the row.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function adjustLocked(AttendanceDay $day, array $attributes, ?AttendancePeriodLock $lock): AttendanceDay
    {
        if (! $day->isLocked() && $lock !== null) {
            $day->forceFill(['attendance_period_lock_id' => $lock->id])->saveQuietly();
        }

        $adjustments = $day->adjustments()->get();
        $lastHash = $adjustments->last()?->calculation_hash ?? $day->calculation_hash;

        if ($lastHash === $attributes['calculation_hash']) {
            return $day;
        }

        $effective = [];

        foreach (AttendanceDay::MINUTE_FIELDS as $field) {
            $effective[$field] = $day->{$field} + (int) $adjustments->sum(str_replace('_minutes', '', $field).'_minutes_delta');
        }

        $before = $adjustments->last()?->status_after ?? $day->status->value;
        $deltas = [];

        foreach (AttendanceDay::MINUTE_FIELDS as $field) {
            $deltas[str_replace('_minutes', '', $field).'_minutes_delta'] = (int) $attributes[$field] - $effective[$field];
        }

        $after = $attributes['status'] instanceof BackedEnum ? $attributes['status']->value : (string) $attributes['status'];

        if ($before === $after && array_filter($deltas) === []) {
            return $day;
        }

        AttendanceAdjustment::create([
            'attendance_day_id' => $day->id,
            'employee_id' => $day->employee_id,
            'work_date' => $day->work_date,
            'status_before' => $before,
            'status_after' => $after,
            ...$deltas,
            'reason' => 'recalculated after the period was locked',
            'attendance_policy_version_id' => $attributes['attendance_policy_version_id'],
            'calculation' => $attributes['calculation'],
            'calculation_hash' => $attributes['calculation_hash'],
            'created_by' => auth()->id(),
        ]);

        return $day;
    }

    /**
     * @param  list<PunchInput>  $punches
     * @param  list<int>  $duplicates
     */
    private function linkPunches(AttendanceDay $day, array $punches, array $duplicates): void
    {
        $used = array_map(fn (PunchInput $punch): int => $punch->id, $punches);

        if ($used !== []) {
            AttendancePunch::query()->whereIn('id', $used)->update(['attendance_day_id' => $day->id, 'is_duplicate' => false]);
        }

        if ($duplicates !== []) {
            AttendancePunch::query()->whereIn('id', $duplicates)->update(['attendance_day_id' => $day->id, 'is_duplicate' => true]);
        }
    }

    /**
     * Rows of the date that the new result no longer has (e.g. the shift changed).
     *
     * @param  Collection<int|string, AttendanceDay>  $existing
     * @param  list<int>  $keep
     */
    private function removeStale(Collection $existing, array $keep, CarbonImmutable $date, ResolvedSchedule $resolved): void
    {
        foreach ($existing as $key => $day) {
            if (in_array((int) $key, $keep, true)) {
                continue;
            }

            $locked = $day->isLocked() || AttendancePeriodLock::query()->covering($date->format('Y-m-d'), $day->branch_id)->exists();

            if (! $locked) {
                $day->delete();

                continue;
            }

            $adjustments = $day->adjustments()->get();

            if ($adjustments->last()?->status_after === 'removed') {
                continue;
            }

            $deltas = [];

            foreach (AttendanceDay::MINUTE_FIELDS as $field) {
                $delta = str_replace('_minutes', '', $field).'_minutes_delta';
                $deltas[$delta] = -($day->{$field} + (int) $adjustments->sum($delta));
            }

            AttendanceAdjustment::create([
                'attendance_day_id' => $day->id, 'employee_id' => $day->employee_id, 'work_date' => $day->work_date,
                'status_before' => $adjustments->last()?->status_after ?? $day->status->value, 'status_after' => 'removed', ...$deltas,
                'reason' => $resolved->isEmployed() ? 'shift no longer scheduled after the period was locked' : 'employee not employed on this date',
                'attendance_policy_version_id' => null, 'calculation' => null,
                'calculation_hash' => hash('sha256', 'removed:'.$day->id), 'created_by' => auth()->id(),
            ]);
        }
    }

    private function markProcessed(int $employeeId, CarbonImmutable $date): void
    {
        AttendanceRawEvent::query()->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('status', RawEventStatus::Pending)
            ->where(fn ($query) => $query->whereDate('work_date', $date->format('Y-m-d'))
                ->orWhere(fn ($query) => $query->whereNull('work_date')
                    ->whereBetween('occurred_at', [$date->subDay()->startOfDay(), $date->addDays(2)->startOfDay()])))
            ->update(['status' => RawEventStatus::Processed, 'processed_at' => now()]);
    }
}
