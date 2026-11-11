<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Enums\AttendanceStatusCategory;
use App\Enums\BreakMode;
use App\Enums\BreakType;
use App\Enums\MissingPunchAction;
use App\Enums\OvertimeStatus;
use App\Enums\PunchDirection;
use App\Enums\RawEventSource;
use App\Models\Shift;
use App\Models\ShiftBreak;
use App\Services\Attendance\Data\DayContext;
use App\Services\Attendance\Data\PunchInput;
use Carbon\CarbonImmutable;

/**
 * The attendance rules, as a pure function: DayContext in, day figures out.
 * Every number comes from the shift or the policy version; every decision is
 * written to the "steps" of the calculation snapshot so the breakdown screen
 * (and payroll audits) can show why a day came out as it did.
 *
 * Summary of the rules:
 * - Status precedence: manual day status > approved leave > no shift (rest day /
 *   holiday / unscheduled working day) > holiday with a shift > punches.
 * - Pairs: PunchPairer. Missing check-in / check-out: policy missing_punch_action
 *   (incomplete | assume the scheduled time | absent).
 * - Worked = time inside pairs + paid break time taken, minus unpaid break
 *   time not already taken as a punched gap (break_mode "scheduled": fixed
 *   breaks by overlap, flexible ones in full once more than half the shift
 *   was present) or nothing more (break_mode "actual").
 * - Late: first check-in after start (+ grace; flexible shifts: core start),
 *   counted from the start or from the end of the grace period, rounded up.
 * - Early leave: last check-out before end (- grace; flexible: core end), rounded up.
 * - Shortfall (flexible): required minutes not worked.
 * - Overtime: after the end (+ before the start if the policy says so); for
 *   flexible shifts worked minus required; on rest days / holidays all worked
 *   time when rest_day_work_is_overtime. Below the minimum it is 0; rounded
 *   down; capped at the daily maximum.
 */
class DayCalculator
{
    public function __construct(private readonly PunchPairer $pairer) {}

    /**
     * @return array<string, mixed>
     */
    public function calculate(DayContext $context): array
    {
        $rules = $context->rules;
        $shift = $context->shift;
        $steps = [];
        $flags = [];

        $paired = $this->pairer->pair($context->punches);
        $pairs = $paired['pairs'];
        $assumedIn = $assumedOut = false;
        $missingIn = $paired['orphan_out'] !== null && $pairs === [];
        $missingOut = $paired['open_in'] !== null;

        if ($paired['ignored'] !== []) {
            $flags[] = 'extra_punches';
            $steps[] = $this->step('ignored_punches', count($paired['ignored']), 'repeated in / out punches not used');
        }

        if ($shift !== null && ($missingIn || $missingOut) && $rules->missing_punch_action === MissingPunchAction::AssumeSchedule) {
            if ($missingOut && $paired['open_in']->at->lt($shift->endAt)) {
                $pairs[] = [$paired['open_in'], $this->synthetic($shift->endAt, PunchDirection::Out)];
                $assumedOut = true;
                $missingOut = false;
            }

            if ($missingIn && $paired['orphan_out']->at->gt($shift->startAt)) {
                array_unshift($pairs, [$this->synthetic($shift->startAt, PunchDirection::In), $paired['orphan_out']]);
                $assumedIn = true;
                $missingIn = false;
            }

            $steps[] = $this->step('missing_punch', 'assume_schedule', 'missing punches replaced by the scheduled time');
        }

        if ($missingIn) {
            $flags[] = 'missing_check_in';
        }

        if ($missingOut) {
            $flags[] = 'missing_check_out';
        }

        if ($assumedIn) {
            $flags[] = 'assumed_check_in';
        }

        if ($assumedOut) {
            $flags[] = 'assumed_check_out';
        }

        $firstIn = $pairs[0][0]->at ?? $paired['open_in']?->at;
        $lastOut = $pairs !== [] ? $pairs[array_key_last($pairs)][1]->at : $paired['orphan_out']?->at;
        $presence = 0;
        $gaps = 0;

        foreach ($pairs as $index => [$in, $out]) {
            $presence += $this->minutes($in->at, $out->at);

            if ($index > 0) {
                $gaps += $this->minutes($pairs[$index - 1][1]->at, $in->at);
            }
        }

        $steps[] = $this->step('pairs', count($pairs), 'complete in/out pairs');
        $steps[] = $this->step('presence_minutes', $presence, 'time between check-in and check-out of every pair');
        $steps[] = $this->step('gap_minutes', $gaps, 'time between pairs (breaks taken by punching out)');

        [$worked, $breakMinutes] = $this->workedMinutes($context, $pairs, $presence, $gaps, $steps, $flags);

        $result = [
            'status' => null,
            'attendance_status_id' => null,
            'scheduled_minutes' => $shift?->expectedMinutes ?? 0,
            'first_in_at' => $firstIn,
            'last_out_at' => $lastOut,
            'worked_minutes' => $worked,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'shortfall_minutes' => 0,
            'break_minutes' => $breakMinutes,
            'overtime_minutes' => 0,
        ];

        $hasPunches = $context->punches !== [];
        $status = $this->baseStatus($context, $hasPunches, $result, $steps, $flags);

        if ($status === null && $shift !== null) {
            $status = $this->shiftStatus($context, $pairs, $firstIn, $lastOut, $assumedIn, $assumedOut, $missingIn, $missingOut, $result, $steps, $flags);
        }

        $result['status'] = $status ?? AttendanceDayStatus::Present;
        $this->overtime($context, $result, $firstIn, $lastOut, $steps);

        $result['overtime_status'] = $result['overtime_minutes'] === 0
            ? OvertimeStatus::None
            : ($rules->overtime_requires_approval ? OvertimeStatus::Pending : OvertimeStatus::Approved);

        $sources = array_map(fn (PunchInput $punch): string => $punch->source->value, $context->punches);

        if ($context->markSource !== null) {
            $sources[] = $context->markSource->value;
        }

        $sources = array_values(array_unique($sources));
        sort($sources);
        $isManual = array_intersect($sources, [RawEventSource::Manual->value, RawEventSource::Import->value]) !== [];

        if ($isManual) {
            $flags[] = 'manual';
        }

        $flags = array_values(array_unique($flags));
        sort($flags);

        return [
            ...$result,
            'flags' => $flags,
            'sources' => $sources,
            'is_manual' => $isManual,
            'pairs' => array_map(fn (array $pair): array => [
                'in' => $pair[0]->at->toIso8601ZuluString(), 'out' => $pair[1]->at->toIso8601ZuluString(),
                'in_punch_id' => $pair[0]->id ?: null, 'out_punch_id' => $pair[1]->id ?: null,
            ], $pairs),
            'ignored_punch_ids' => $paired['ignored'],
            'steps' => $steps,
        ];
    }

    /**
     * Statuses that do not depend on the punches against a shift: manual day
     * status, leave, days without a shift, holidays.
     *
     * @param  array<string, mixed>  $result
     * @param  list<array<string, mixed>>  $steps
     * @param  list<string>  $flags
     */
    private function baseStatus(DayContext $context, bool $hasPunches, array &$result, array &$steps, array &$flags): ?AttendanceDayStatus
    {
        if ($context->mark !== null) {
            $mark = $context->mark;
            $result['attendance_status_id'] = $mark->id;
            $steps[] = $this->step('manual_status', $mark->code, 'day status recorded manually (raw event #'.$context->markEventId.')');

            if ($mark->category === AttendanceStatusCategory::Present && $mark->counts_as_worked && $result['worked_minutes'] === 0) {
                $result['worked_minutes'] = $result['scheduled_minutes'];
                $steps[] = $this->step('worked_minutes', $result['worked_minutes'], 'status counts as worked: scheduled minutes');
            }

            if ($mark->category !== AttendanceStatusCategory::Present) {
                $result['worked_minutes'] = $mark->category === AttendanceStatusCategory::Absent ? 0 : $result['worked_minutes'];
            }

            return match ($mark->category) {
                AttendanceStatusCategory::Present => AttendanceDayStatus::Present,
                AttendanceStatusCategory::Absent => AttendanceDayStatus::Absent,
                AttendanceStatusCategory::OnLeave => AttendanceDayStatus::OnLeave,
                AttendanceStatusCategory::RestDay => AttendanceDayStatus::RestDay,
            };
        }

        if ($context->leave !== null && ! $context->leave->halfDay) {
            $steps[] = $this->step('leave', $context->leave->reference, 'approved leave');

            if ($hasPunches) {
                $flags[] = 'worked_on_leave';
            }

            return AttendanceDayStatus::OnLeave;
        }

        if ($context->leave?->halfDay) {
            $flags[] = 'half_day_leave';
        }

        if ($context->shift === null) {
            if ($hasPunches) {
                $flags[] = $context->holiday ? 'holiday_work' : ($context->isRestDay ? 'rest_day_work' : 'unscheduled_work');
                $steps[] = $this->step('status', 'present', 'punches on a day without a shift');

                return AttendanceDayStatus::Present;
            }

            if ($context->holiday !== null) {
                return AttendanceDayStatus::Holiday;
            }

            if ($context->isRestDay) {
                return AttendanceDayStatus::RestDay;
            }

            $flags[] = 'unscheduled';
            $steps[] = $this->step('status', 'absent', 'working day under the weekend rule, no schedule and no punches');

            return AttendanceDayStatus::Absent;
        }

        if ($context->holiday !== null) {
            if (! $hasPunches) {
                return AttendanceDayStatus::Holiday;
            }

            $flags[] = 'holiday_work';
            $steps[] = $this->step('status', 'present', 'worked on a public holiday');

            return AttendanceDayStatus::Present;
        }

        return null;
    }

    /**
     * @param  list<array{0: PunchInput, 1: PunchInput}>  $pairs
     * @param  array<string, mixed>  $result
     * @param  list<array<string, mixed>>  $steps
     * @param  list<string>  $flags
     */
    private function shiftStatus(
        DayContext $context,
        array $pairs,
        ?CarbonImmutable $firstIn,
        ?CarbonImmutable $lastOut,
        bool $assumedIn,
        bool $assumedOut,
        bool $missingIn,
        bool $missingOut,
        array &$result,
        array &$steps,
        array &$flags,
    ): AttendanceDayStatus {
        $rules = $context->rules;
        $shift = $context->shift;

        if ($context->punches === []) {
            if ($context->leave?->halfDay) {
                return AttendanceDayStatus::OnLeave;
            }

            $steps[] = $this->step('status', 'absent', 'scheduled shift without any punch');

            return AttendanceDayStatus::Absent;
        }

        $lateReference = $shift->shift->isFlexible() ? $shift->coreStartAt : $shift->startAt;
        $earlyReference = $shift->shift->isFlexible() ? $shift->coreEndAt : $shift->endAt;
        $lateGrace = $shift->shift->late_grace_minutes ?? $rules->late_grace_minutes;
        $earlyGrace = $shift->shift->early_leave_grace_minutes ?? $rules->early_leave_grace_minutes;

        if ($lateReference !== null && $firstIn !== null && ! $assumedIn && ! $missingIn && ! $context->leave?->halfDay) {
            $lateBy = $firstIn->gt($lateReference) ? $this->minutes($lateReference, $firstIn) : 0;

            if ($lateBy > $lateGrace) {
                $late = $rules->late_count_from_shift_start ? $lateBy : $lateBy - $lateGrace;
                $result['late_minutes'] = $this->roundUp($late, $rules->late_rounding_minutes);
            }

            $steps[] = $this->step('late_minutes', $result['late_minutes'], "arrived {$lateBy} min after start, grace {$lateGrace}, rounding {$rules->late_rounding_minutes}");
        }

        if ($earlyReference !== null && $lastOut !== null && ! $assumedOut && ! $missingOut && ! $context->leave?->halfDay) {
            $earlyBy = $lastOut->lt($earlyReference) ? $this->minutes($lastOut, $earlyReference) : 0;

            if ($earlyBy > $earlyGrace) {
                $result['early_leave_minutes'] = $this->roundUp($earlyBy, $rules->early_leave_rounding_minutes);
            }

            $steps[] = $this->step('early_leave_minutes', $result['early_leave_minutes'], "left {$earlyBy} min before end, grace {$earlyGrace}");
        }

        if ($shift->shift->isFlexible()) {
            $result['shortfall_minutes'] = max(0, $shift->expectedMinutes - $result['worked_minutes']);
            $steps[] = $this->step('shortfall_minutes', $result['shortfall_minutes'], 'required '.$shift->expectedMinutes.' min');
        }

        if ($missingIn || $missingOut) {
            $action = $rules->missing_punch_action;
            $steps[] = $this->step('missing_punch', $action->value, 'policy action for a missing check-in / check-out');

            return $action === MissingPunchAction::Absent ? AttendanceDayStatus::Absent : AttendanceDayStatus::Incomplete;
        }

        if ($rules->late_absent_after_minutes !== null && $result['late_minutes'] > $rules->late_absent_after_minutes) {
            $flags[] = 'late_as_absent';
            $steps[] = $this->step('status', 'absent', 'late more than '.$rules->late_absent_after_minutes.' min');

            return AttendanceDayStatus::Absent;
        }

        if ($rules->min_worked_minutes_for_present !== null && $result['worked_minutes'] < $rules->min_worked_minutes_for_present) {
            $flags[] = 'insufficient_hours';
            $steps[] = $this->step('status', 'absent', 'worked less than '.$rules->min_worked_minutes_for_present.' min');

            return AttendanceDayStatus::Absent;
        }

        if ($result['late_minutes'] > 0) {
            return AttendanceDayStatus::Late;
        }

        return AttendanceDayStatus::Present;
    }

    /**
     * @param  list<array{0: PunchInput, 1: PunchInput}>  $pairs
     * @param  list<array<string, mixed>>  $steps
     * @param  list<string>  $flags
     * @return array{0: int, 1: int} worked minutes, break minutes
     */
    private function workedMinutes(DayContext $context, array $pairs, int $presence, int $gaps, array &$steps, array &$flags): array
    {
        $shift = $context->shift;
        $rules = $context->rules;

        if ($shift === null || $pairs === []) {
            return [$presence, $gaps];
        }

        $paid = $shift->shift->paidBreakMinutes();
        $paidTaken = min($gaps, $paid);
        $deducted = 0;

        if ($rules->break_mode === BreakMode::Scheduled) {
            foreach ($shift->shift->breaks as $break) {
                /** @var ShiftBreak $break */
                if ($break->is_paid) {
                    continue;
                }

                if ($break->type === BreakType::Fixed && $break->start_time && $break->end_time) {
                    $start = $this->breakInstant($shift->startAt, (string) $break->start_time, $shift->shift->start_time);
                    $end = $this->breakInstant($shift->startAt, (string) $break->end_time, $shift->shift->start_time);
                    $end = $end->lte($start) ? $end->addDay() : $end;
                    $deducted += $this->overlap($pairs, $start, $end);
                } elseif ($presence * 2 > $shift->shift->spanMinutes()) {
                    $deducted += max(0, (int) $break->duration_minutes - max(0, $gaps - $paidTaken));
                }
            }

            $steps[] = $this->step('unpaid_break_deducted', $deducted, 'scheduled unpaid breaks inside the worked time');
        } elseif ($rules->max_break_minutes !== null && $gaps > $rules->max_break_minutes) {
            $flags[] = 'excess_break';
            $steps[] = $this->step('excess_break_minutes', $gaps - $rules->max_break_minutes, 'breaks longer than the policy maximum');
        }

        if ($paidTaken > 0) {
            $steps[] = $this->step('paid_break_minutes', $paidTaken, 'paid break time taken, counted as worked');
        }

        $worked = max(0, $presence + $paidTaken - $deducted);
        $steps[] = $this->step('worked_minutes', $worked, 'presence + paid breaks taken - unpaid breaks deducted');

        return [$worked, $gaps + $deducted];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  list<array<string, mixed>>  $steps
     */
    private function overtime(DayContext $context, array &$result, ?CarbonImmutable $firstIn, ?CarbonImmutable $lastOut, array &$steps): void
    {
        $rules = $context->rules;
        $status = $result['status'];

        if (! $rules->overtime_enabled || ! in_array($status, [AttendanceDayStatus::Present, AttendanceDayStatus::Late], true) || $context->mark !== null) {
            return;
        }

        $shift = $context->shift;
        $offDay = $shift === null || $context->holiday !== null;

        if ($offDay) {
            if ($shift === null && ! $context->isRestDay && $context->holiday === null) {
                return; // unscheduled working day: nothing to compare with
            }

            $candidate = $rules->rest_day_work_is_overtime ? $result['worked_minutes'] : 0;
            $basis = 'worked on a rest day / holiday';
        } elseif ($shift->shift->isFlexible()) {
            $candidate = max(0, $result['worked_minutes'] - $shift->expectedMinutes);
            $basis = 'worked beyond the required minutes';
        } else {
            $after = $lastOut !== null && $lastOut->gt($shift->endAt) ? $this->minutes($shift->endAt, $lastOut) : 0;
            $before = $rules->overtime_count_early_arrival && $firstIn !== null && $firstIn->lt($shift->startAt) ? $this->minutes($firstIn, $shift->startAt) : 0;
            $candidate = $after + $before;
            $basis = "after the end {$after} min, before the start {$before} min";
        }

        $overtime = $candidate < $rules->overtime_min_minutes ? 0 : $this->roundDown($candidate, $rules->overtime_rounding_minutes);

        if ($rules->overtime_max_daily_minutes !== null) {
            $overtime = min($overtime, $rules->overtime_max_daily_minutes);
        }

        $result['overtime_minutes'] = $overtime;
        $steps[] = $this->step('overtime_minutes', $overtime, "{$basis}; minimum {$rules->overtime_min_minutes}, rounding {$rules->overtime_rounding_minutes}");
    }

    /**
     * Total minutes of the pairs inside [start, end).
     *
     * @param  list<array{0: PunchInput, 1: PunchInput}>  $pairs
     */
    private function overlap(array $pairs, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $total = 0;

        foreach ($pairs as [$in, $out]) {
            $from = $in->at->max($start);
            $to = $out->at->min($end);

            if ($to->gt($from)) {
                $total += $this->minutes($from, $to);
            }
        }

        return $total;
    }

    /**
     * Instant of a break time within the shift that starts at $shiftStart.
     */
    private function breakInstant(CarbonImmutable $shiftStart, string $time, string $shiftStartTime): CarbonImmutable
    {
        $offset = ((Shift::minuteOfDay($time) - Shift::minuteOfDay($shiftStartTime)) + 1440) % 1440;

        return $shiftStart->addMinutes($offset);
    }

    private function synthetic(CarbonImmutable $at, PunchDirection $direction): PunchInput
    {
        return new PunchInput(0, 0, $at, $direction, RawEventSource::Manual);
    }

    private function minutes(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return intdiv(max(0, $to->getTimestamp() - $from->getTimestamp()), 60);
    }

    private function roundUp(int $minutes, int $step): int
    {
        return $step > 1 ? (int) (ceil($minutes / $step) * $step) : $minutes;
    }

    private function roundDown(int $minutes, int $step): int
    {
        return $step > 1 ? intdiv($minutes, $step) * $step : $minutes;
    }

    /**
     * @return array{key: string, value: mixed, note: string}
     */
    private function step(string $key, mixed $value, string $note): array
    {
        return ['key' => $key, 'value' => $value, 'note' => $note];
    }
}
