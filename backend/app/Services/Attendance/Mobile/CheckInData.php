<?php

namespace App\Services\Attendance\Mobile;

use App\Enums\PunchDirection;
use App\Services\Attendance\Face\Data\FaceSample;
use Carbon\CarbonImmutable;

/**
 * A mobile check-in / check-out request as sent by the app (validated by
 * App\Http\Requests\Api\V1\MobileAttendanceRequest). Nothing in it is trusted:
 * MobileCheckIn validates everything on the server.
 */
final readonly class CheckInData
{
    public function __construct(
        public string $requestUuid,
        public PunchDirection $direction,
        public float $latitude,
        public float $longitude,
        public float $accuracyMeters,
        public bool $isMockLocation,
        public ?CarbonImmutable $deviceTime,
        public ?string $deviceIdentifier,
        public ?string $platform,
        public ?string $appVersion,
        public ?FaceSample $face,
        public ?string $ipAddress,
    ) {}
}
