<?php

namespace App\Services\Attendance;

use App\Enums\ManualEntryAction;
use App\Enums\PunchDirection;
use App\Enums\RawEventKind;
use App\Enums\RawEventSource;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceDay;
use App\Models\AttendanceManualEntry;
use App\Models\AttendancePunch;
use App\Models\AttendanceRawEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\Data\RawEventData;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns manual input (bulk entries, imports, approved corrections) into raw
 * events with source manual / import, so the one engine calculates them like
 * any device punch. Existing raw events are never edited: earlier manual
 * input for the same day is voided ("superseded") and new events recorded.
 */
class ManualAttendanceWriter
{
    public function __construct(
        private readonly RawEventRecorder $recorder,
        private readonly ScheduleResolver $schedules,
        private readonly AttendanceRecalculation $recalculation,
    ) {}

    public function applyEntry(AttendanceManualEntry $entry, ?User $user, RawEventSource $source, string $reference): void
    {
        $employee = $entry->employee()->withoutGlobalScopes()->firstOrFail();
        $date = AttendanceClock::date($entry->work_date);
        $resolved = $this->schedules->for($employee, $date);
        $timezone = $resolved->timezone;

        $this->supersedeManual($employee, $date, $user, 'superseded by '.$reference, $entry);

        $base = fn (string $suffix) => "{$source->value}:entry:{$entry->id}:{$suffix}";

        if ($entry->action === ManualEntryAction::Status) {
            $this->recorder->record(RawEventData::dayMark(
                $source, $employee->id, $date->format('Y-m-d'), $entry->attendance_status_id, $timezone, $base('mark'),
                $entry->shift_id, $reference, $entry->note, $entry, $user,
            ), process: 'none');
        } else {
            $shifts = $entry->shift_id
                ? array_values(array_filter($resolved->shifts, fn ($shift): bool => $shift->key() === $entry->shift_id))
                : $resolved->shifts;

            if ($entry->action !== ManualEntryAction::Present) {
                $shifts = array_slice($shifts, 0, 1);
            }

            foreach ($shifts as $index => $shift) {
                $in = match ($entry->action) {
                    ManualEntryAction::Present => $shift->startAt,
                    default => CarbonImmutable::instance($entry->check_in_at ?? $shift->startAt),
                };
                $out = match ($entry->action) {
                    ManualEntryAction::Present, ManualEntryAction::Late => $shift->endAt,
                    default => $entry->check_out_at ? CarbonImmutable::instance($entry->check_out_at) : null,
                };

                $this->punch($source, $employee, $in, PunchDirection::In, $timezone, $date, $shift->key(), $base("in:{$index}"), $reference, $entry->note, $entry, $user);

                if ($out !== null) {
                    $this->punch($source, $employee, $out, PunchDirection::Out, $timezone, $date, $shift->key(), $base("out:{$index}"), $reference, $entry->note, $entry, $user);
                }
            }

            if ($shifts === [] && $entry->check_in_at !== null) {
                // A day without a shift (e.g. work on a rest day recorded with custom times).
                $this->punch($source, $employee, CarbonImmutable::instance($entry->check_in_at), PunchDirection::In, $timezone, $date, null, $base('in:0'), $reference, $entry->note, $entry, $user);

                if ($entry->check_out_at !== null) {
                    $this->punch($source, $employee, CarbonImmutable::instance($entry->check_out_at), PunchDirection::Out, $timezone, $date, null, $base('out:0'), $reference, $entry->note, $entry, $user);
                }
            }
        }
    }

    /**
     * Apply an approved (or directly applied) correction.
     */
    public function applyCorrection(AttendanceCorrection $correction, ?User $user): void
    {
        $employee = $correction->employee()->withoutGlobalScopes()->firstOrFail();
        $date = AttendanceClock::date($correction->work_date);
        $timezone = $this->schedules->for($employee, $date)->timezone;
        $reference = 'correction:'.$correction->id;
        $reason = 'corrected by '.$reference;

        $toVoid = AttendanceRawEvent::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereIn('id', $correction->void_raw_event_ids ?? [])
            ->notVoided()->get();

        // A corrected check-in / check-out replaces the recorded ones of that day (and shift).
        $replaced = array_filter([
            $correction->check_in_at ? PunchDirection::In : null,
            $correction->check_out_at ? PunchDirection::Out : null,
        ]);

        if ($replaced !== []) {
            $dayIds = AttendanceDay::query()->withoutGlobalScopes()
                ->where('employee_id', $employee->id)->whereDate('work_date', $date->format('Y-m-d'))
                ->when($correction->shift_id, fn ($query, $shiftId) => $query->where('shift_id', $shiftId))
                ->pluck('id');
            $directions = count($replaced) === 2 ? [...$replaced, PunchDirection::Unknown] : $replaced;

            $toVoid = $toVoid->merge(AttendanceRawEvent::query()->withoutGlobalScopes()->notVoided()
                ->whereIn('id', AttendancePunch::query()->whereIn('attendance_day_id', $dayIds)->whereIn('direction', $directions)->select('attendance_raw_event_id'))
                ->get());
        }

        foreach ($toVoid->unique('id') as $event) {
            $this->recorder->void($event, $user, $reason, process: 'none');
        }

        if ($correction->attendance_status_id !== null) {
            $this->recorder->record(RawEventData::dayMark(
                RawEventSource::Manual, $employee->id, $date->format('Y-m-d'), $correction->attendance_status_id, $timezone,
                "correction:{$correction->id}:mark", $correction->shift_id, $reference, $correction->reason, $correction, $user,
            ), process: 'none');
        } elseif ($correction->check_in_at || $correction->check_out_at) {
            // Punches decide again: clear an earlier manual day status.
            $this->clearMarks($employee, $date, $user, $reason);
        }

        foreach (['in' => $correction->check_in_at, 'out' => $correction->check_out_at] as $direction => $at) {
            if ($at !== null) {
                $this->punch(RawEventSource::Manual, $employee, CarbonImmutable::instance($at), PunchDirection::from($direction), $timezone, $date,
                    $correction->shift_id, "correction:{$correction->id}:{$direction}", $reference, $correction->reason, $correction, $user);
            }
        }

        $this->recalculation->now($employee->id, [$date->format('Y-m-d')]);
    }

    /**
     * Void earlier manual / import events of the day (device and mobile punches are never touched).
     */
    private function supersedeManual(Employee $employee, CarbonImmutable $date, ?User $user, string $reason, Model $except): void
    {
        AttendanceRawEvent::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereIn('source', [RawEventSource::Manual, RawEventSource::Import])
            ->whereDate('work_date', $date->format('Y-m-d'))
            ->whereNot(fn ($query) => $query->where('origin_type', $except->getMorphClass())->where('origin_id', $except->getKey()))
            // An entry for one shift of a multi-shift day leaves the other shift's manual input alone.
            ->when($except->getAttribute('shift_id'), fn ($query, $shiftId) => $query->where(fn ($query) => $query->whereNull('shift_id')->orWhere('shift_id', $shiftId)))
            ->notVoided()
            ->get()
            ->each(fn (AttendanceRawEvent $event) => $this->recorder->void($event, $user, $reason, process: 'none'));
    }

    private function clearMarks(Employee $employee, CarbonImmutable $date, ?User $user, string $reason): void
    {
        AttendanceRawEvent::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)->where('kind', RawEventKind::DayMark)
            ->whereDate('work_date', $date->format('Y-m-d'))->notVoided()->get()
            ->each(fn (AttendanceRawEvent $event) => $this->recorder->void($event, $user, $reason, process: 'none'));
    }

    private function punch(
        RawEventSource $source,
        Employee $employee,
        CarbonImmutable $at,
        PunchDirection $direction,
        string $timezone,
        CarbonImmutable $date,
        ?int $shiftId,
        string $key,
        string $reference,
        ?string $note,
        Model $origin,
        ?User $user,
    ): void {
        $this->recorder->record(RawEventData::punch(
            $source, $employee->id, $at, $direction, $timezone, $key, $date->format('Y-m-d'), $shiftId, $reference, null, $note, $origin, $user,
        ), process: 'none');
    }
}
