<?php

namespace App\Services\Attendance\Data;

use App\Models\Shift;
use Carbon\CarbonImmutable;

/**
 * One expected shift of an employee on a date, with its instants in UTC.
 */
final readonly class ScheduledShift
{
    public function __construct(
        public Shift $shift,
        public CarbonImmutable $startAt,
        public CarbonImmutable $endAt,
        public CarbonImmutable $windowStart,
        public CarbonImmutable $windowEnd,
        public ?CarbonImmutable $coreStartAt,
        public ?CarbonImmutable $coreEndAt,
        public int $expectedMinutes,
    ) {}

    public function key(): int
    {
        return (int) $this->shift->id;
    }

    /**
     * Whether an instant falls inside the check-in / check-out window.
     */
    public function windowContains(CarbonImmutable $instant): bool
    {
        return $instant->gte($this->windowStart) && $instant->lte($this->windowEnd);
    }

    /**
     * Minutes between an instant and the shift span (0 when inside it).
     */
    public function distanceTo(CarbonImmutable $instant): int
    {
        if ($instant->lt($this->startAt)) {
            return (int) $instant->diffInMinutes($this->startAt);
        }

        if ($instant->gt($this->endAt)) {
            return (int) $this->endAt->diffInMinutes($instant);
        }

        return 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $timezone): array
    {
        return [
            'shift_id' => $this->shift->id,
            'code' => $this->shift->code,
            'type' => $this->shift->type->value,
            'start_at' => $this->startAt->toIso8601ZuluString(),
            'end_at' => $this->endAt->toIso8601ZuluString(),
            'start_local' => $this->startAt->setTimezone($timezone)->format('Y-m-d H:i'),
            'end_local' => $this->endAt->setTimezone($timezone)->format('Y-m-d H:i'),
            'window_start' => $this->windowStart->toIso8601ZuluString(),
            'window_end' => $this->windowEnd->toIso8601ZuluString(),
            'core_start_at' => $this->coreStartAt?->toIso8601ZuluString(),
            'core_end_at' => $this->coreEndAt?->toIso8601ZuluString(),
            'expected_minutes' => $this->expectedMinutes,
            'unpaid_break_minutes' => $this->shift->unpaidBreakMinutes(),
            'paid_break_minutes' => $this->shift->paidBreakMinutes(),
            'crosses_midnight' => $this->shift->crossesMidnight(),
        ];
    }
}
