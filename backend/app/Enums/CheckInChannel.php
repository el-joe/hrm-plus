<?php

namespace App\Enums;

/**
 * Where a self-service check-in / check-out attempt was made: the Flutter app
 * (POST /api/v1/me/attendance/*) or the employee web portal
 * (POST /portal/attendance/check-*). Both go through the same validation
 * (App\Services\Attendance\Mobile\MobileCheckIn); the channel decides the
 * raw-event source, the idempotency prefix and the policy switch that applies.
 */
enum CheckInChannel: string
{
    case Mobile = 'mobile';
    case Web = 'web';

    public function label(): string
    {
        return __('mobile.enums.channels.'.$this->value);
    }

    public function rawEventSource(): RawEventSource
    {
        return match ($this) {
            self::Mobile => RawEventSource::MobileGps,
            self::Web => RawEventSource::WebPortal,
        };
    }

    /**
     * Prefix of the raw event idempotency key ("{prefix}:{request uuid}").
     */
    public function idempotencyPrefix(): string
    {
        return $this->value;
    }
}
