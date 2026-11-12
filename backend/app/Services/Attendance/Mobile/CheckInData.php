<?php

namespace App\Services\Attendance\Mobile;

use App\Enums\CheckInChannel;
use App\Enums\PunchDirection;
use App\Services\Attendance\Face\Data\FaceSample;
use Carbon\CarbonImmutable;

/**
 * A self-service check-in / check-out request as sent by the mobile app
 * (App\Http\Requests\Api\V1\MobileAttendanceRequest) or the web portal
 * (App\Http\Requests\Employee\PortalCheckInRequest). Nothing in it is
 * trusted: MobileCheckIn validates everything on the server.
 *
 * Coordinates are null only for a web attempt whose browser gave no position
 * (accepted only when the policy lets web check-ins skip the geofence).
 */
final readonly class CheckInData
{
    public function __construct(
        public string $requestUuid,
        public PunchDirection $direction,
        public ?float $latitude,
        public ?float $longitude,
        public ?float $accuracyMeters,
        public bool $isMockLocation,
        public ?CarbonImmutable $deviceTime,
        public ?string $deviceIdentifier,
        public ?string $platform,
        public ?string $appVersion,
        public ?FaceSample $face,
        public ?string $ipAddress,
        public CheckInChannel $channel = CheckInChannel::Mobile,
    ) {}

    public function hasPosition(): bool
    {
        return $this->latitude !== null && $this->longitude !== null && $this->accuracyMeters !== null;
    }
}
