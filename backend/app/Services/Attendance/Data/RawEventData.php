<?php

namespace App\Services\Attendance\Data;

use App\Enums\PunchDirection;
use App\Enums\RawEventKind;
use App\Enums\RawEventSource;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Input of RawEventRecorder::record(). Build with the named constructors:
 *
 *     RawEventData::punch(RawEventSource::Device, $employeeId, $at, PunchDirection::In, 'Africa/Cairo',
 *         idempotencyKey: "device:{$serial}:{$deviceUserId}:{$at->getTimestamp()}", sourceReference: "{$serial}/{$deviceUserId}");
 *     RawEventData::dayMark(RawEventSource::Manual, $employeeId, '2026-11-02', $statusId, $tz, idempotencyKey: '...');
 *
 * The idempotency key must be stable for the same real-world input (a device
 * log id, a mobile request uuid, "entry:{id}:in"): recording it again returns
 * the stored event instead of creating a duplicate (unique index).
 */
final readonly class RawEventData
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function __construct(
        public RawEventKind $kind,
        public RawEventSource $source,
        public ?int $employeeId,
        public DateTimeInterface $occurredAt,
        public string $timezone,
        public string $idempotencyKey,
        public ?PunchDirection $direction = null,
        public ?string $workDate = null,
        public ?int $shiftId = null,
        public ?int $attendanceStatusId = null,
        public ?string $sourceReference = null,
        public ?array $payload = null,
        public ?string $note = null,
        public ?Model $origin = null,
        public ?User $recordedBy = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function punch(
        RawEventSource $source,
        ?int $employeeId,
        DateTimeInterface $occurredAt,
        PunchDirection $direction,
        string $timezone,
        string $idempotencyKey,
        ?string $workDate = null,
        ?int $shiftId = null,
        ?string $sourceReference = null,
        ?array $payload = null,
        ?string $note = null,
        ?Model $origin = null,
        ?User $recordedBy = null,
    ): self {
        return new self(RawEventKind::Punch, $source, $employeeId, $occurredAt, $timezone, $idempotencyKey, $direction, $workDate,
            $shiftId, null, $sourceReference, $payload, $note, $origin, $recordedBy);
    }

    /**
     * A status for a whole day (or one shift of it); null status clears earlier marks.
     */
    public static function dayMark(
        RawEventSource $source,
        int $employeeId,
        string $workDate,
        ?int $attendanceStatusId,
        string $timezone,
        string $idempotencyKey,
        ?int $shiftId = null,
        ?string $sourceReference = null,
        ?string $note = null,
        ?Model $origin = null,
        ?User $recordedBy = null,
    ): self {
        return new self(RawEventKind::DayMark, $source, $employeeId, now(), $timezone, $idempotencyKey, null, $workDate,
            $shiftId, $attendanceStatusId, $sourceReference, null, $note, $origin, $recordedBy);
    }
}
