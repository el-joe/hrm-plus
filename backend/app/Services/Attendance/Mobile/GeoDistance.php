<?php

namespace App\Services\Attendance\Mobile;

/**
 * Great-circle distance (haversine) on the WGS-84 mean radius. Accurate to
 * well under a metre at geofence scale.
 */
final class GeoDistance
{
    public const EARTH_RADIUS_METERS = 6371008.8;

    public static function meters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $phiA = deg2rad($latitudeA);
        $phiB = deg2rad($latitudeB);
        $deltaPhi = deg2rad($latitudeB - $latitudeA);
        $deltaLambda = deg2rad($longitudeB - $longitudeA);

        $a = sin($deltaPhi / 2) ** 2 + cos($phiA) * cos($phiB) * sin($deltaLambda / 2) ** 2;

        return 2 * self::EARTH_RADIUS_METERS * atan2(sqrt($a), sqrt(1 - $a));
    }
}
