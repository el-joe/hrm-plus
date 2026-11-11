<?php

namespace App\Services\Attendance;

use App\Enums\PunchDirection;
use App\Enums\RawEventKind;
use App\Enums\RawEventStatus;
use App\Models\AttendancePunch;
use App\Models\AttendanceRawEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\Data\RawEventData;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * THE way into the attendance system for every source (Phase 5b device
 * adapters, mobile GPS check-in, manual entry, imports):
 *
 *     $event = $recorder->record(RawEventData::punch(...));             // stores + queues processing
 *     $event = $recorder->record($data, process: 'now');                // stores + processes synchronously
 *     $event = $recorder->record($data, process: 'none');               // stores only (bulk: caller recalculates)
 *     $event->wasRecentlyCreated === false                              // it was a duplicate
 *
 * Steps: insert with the unique idempotency key (a duplicate returns the
 * stored row, never a second one), derive the normalised punch, then trigger
 * recalculation of the dates the event can affect. Unmatched events
 * (employee null) wait until matchEmployee() is called.
 */
class RawEventRecorder
{
    public function __construct(private readonly AttendanceRecalculation $recalculation) {}

    /**
     * @param  'queue'|'now'|'none'  $process
     */
    public function record(RawEventData $data, string $process = 'queue'): AttendanceRawEvent
    {
        $this->validate($data);

        $event = new AttendanceRawEvent;
        $event->forceFill([
            'kind' => $data->kind,
            'source' => $data->source,
            'employee_id' => $data->employeeId,
            'occurred_at' => CarbonImmutable::instance($data->occurredAt)->utc(),
            'timezone' => $data->timezone,
            'direction' => $data->direction,
            'work_date' => $data->workDate,
            'shift_id' => $data->shiftId,
            'attendance_status_id' => $data->attendanceStatusId,
            'source_reference' => $data->sourceReference,
            'idempotency_key' => $data->idempotencyKey,
            'payload' => $data->payload,
            'note' => $data->note,
            'origin_type' => $data->origin?->getMorphClass(),
            'origin_id' => $data->origin?->getKey(),
            'recorded_by' => $data->recordedBy?->id,
            'status' => $data->employeeId === null ? RawEventStatus::Unmatched : RawEventStatus::Pending,
        ]);

        try {
            DB::transaction(function () use ($event): void {
                $event->save();
                $this->derivePunch($event);
            });
        } catch (UniqueConstraintViolationException) {
            return AttendanceRawEvent::query()->withoutGlobalScopes()->where('idempotency_key', $data->idempotencyKey)->firstOrFail();
        }

        $this->trigger($event, $process);

        return $event;
    }

    /**
     * Attach an unmatched event (e.g. an unknown device user) to an employee and process it.
     *
     * @param  'queue'|'now'|'none'  $process
     */
    public function matchEmployee(AttendanceRawEvent $event, Employee $employee, string $process = 'queue'): AttendanceRawEvent
    {
        DB::transaction(function () use ($event, $employee): void {
            $event->forceFill(['employee_id' => $employee->id, 'status' => RawEventStatus::Pending])->save();
            $this->derivePunch($event);
        });

        $this->trigger($event, $process);

        return $event;
    }

    /**
     * Invalidate an event (it stays for the record) and recalculate.
     *
     * @param  'queue'|'now'|'none'  $process
     */
    public function void(AttendanceRawEvent $event, ?User $user, string $reason, string $process = 'now'): void
    {
        if ($event->isVoided()) {
            return;
        }

        DB::transaction(function () use ($event, $user, $reason): void {
            $event->forceFill([
                'voided_at' => now(), 'voided_by' => $user?->id, 'void_reason' => $reason, 'status' => RawEventStatus::Void,
            ])->save();
            AttendancePunch::query()->where('attendance_raw_event_id', $event->id)->delete();
        });

        $this->trigger($event, $process);
    }

    /**
     * Local dates an event can affect: its pinned date, or the punch's local
     * date and the days either side (night shifts, early check-in windows).
     *
     * @return list<string>
     */
    public function affectedDates(AttendanceRawEvent $event): array
    {
        if ($event->work_date !== null) {
            return [$event->work_date->format('Y-m-d')];
        }

        $local = AttendanceClock::localDate($event->occurred_at, $event->timezone);

        return [$local->subDay()->format('Y-m-d'), $local->format('Y-m-d'), $local->addDay()->format('Y-m-d')];
    }

    private function derivePunch(AttendanceRawEvent $event): void
    {
        if ($event->kind !== RawEventKind::Punch || $event->employee_id === null || $event->isVoided()) {
            return;
        }

        AttendancePunch::query()->firstOrCreate(['attendance_raw_event_id' => $event->id], [
            'employee_id' => $event->employee_id,
            'punched_at' => $event->occurred_at,
            'direction' => $event->direction ?? PunchDirection::Unknown,
            'source' => $event->source,
        ]);
    }

    /**
     * @param  'queue'|'now'|'none'  $process
     */
    private function trigger(AttendanceRawEvent $event, string $process): void
    {
        if ($process === 'none' || $event->employee_id === null) {
            return;
        }

        $process === 'now'
            ? $this->recalculation->now($event->employee_id, $this->affectedDates($event))
            : $this->recalculation->queue($event->employee_id, $this->affectedDates($event));
    }

    private function validate(RawEventData $data): void
    {
        if ($data->idempotencyKey === '' || mb_strlen($data->idempotencyKey) > 191) {
            throw new InvalidArgumentException('A raw attendance event needs an idempotency key of 1-191 characters.');
        }

        if ($data->kind === RawEventKind::Punch && $data->direction === null) {
            throw new InvalidArgumentException('A punch needs a direction (in, out or unknown).');
        }

        if ($data->kind === RawEventKind::DayMark && ($data->workDate === null || $data->employeeId === null)) {
            throw new InvalidArgumentException('A day mark needs an employee and a work date.');
        }
    }
}
