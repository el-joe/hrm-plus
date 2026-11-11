<?php

namespace App\Services\Attendance\Devices\Data;

use App\Enums\PunchDirection;
use Carbon\CarbonImmutable;

/**
 * One attendance log as read from a device, normalised by its adapter.
 * occurredAt is an absolute instant (the adapter applies the device timezone).
 */
final readonly class DevicePunch
{
    /**
     * @param  array<string, scalar|null>  $raw  original fields worth keeping (small)
     */
    public function __construct(
        public string $deviceUserId,
        public CarbonImmutable $occurredAt,
        public PunchDirection $direction = PunchDirection::Unknown,
        public ?string $deviceLogId = null,
        public ?string $verifyMode = null,
        public array $raw = [],
    ) {}
}
