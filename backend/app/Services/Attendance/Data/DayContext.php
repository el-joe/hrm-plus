<?php

namespace App\Services\Attendance\Data;

use App\Enums\RawEventSource;
use App\Models\AttendancePolicyVersion;
use App\Models\AttendanceStatus;
use App\Models\Holiday;
use Carbon\CarbonImmutable;

/**
 * Everything the calculator needs for one employee / date / shift.
 */
final readonly class DayContext
{
    /**
     * @param  list<PunchInput>  $punches  attributed to this day / shift, sorted, duplicates removed
     * @param  CarbonImmutable|null  $now  processing time (UTC): an open check-in before the shift end is in progress
     */
    public function __construct(
        public CarbonImmutable $date,
        public string $timezone,
        public ?ScheduledShift $shift,
        public bool $isRestDay,
        public ?Holiday $holiday,
        public ?LeaveDay $leave,
        public ?AttendanceStatus $mark,
        public ?int $markEventId,
        public ?RawEventSource $markSource,
        public array $punches,
        public AttendancePolicyVersion $rules,
        public string $scheduleSource,
        public ?CarbonImmutable $now = null,
    ) {}
}
