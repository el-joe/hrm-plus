<?php

namespace App\Services\Attendance\Mobile;

/**
 * Reason codes stored on attendance_mobile_attempts.reasons and returned to
 * the app (labels: lang mobile.reasons.*).
 */
final class AttemptReason
{
    // Rejections.
    public const NOT_EMPLOYED = 'not_employed';

    public const MOBILE_DISABLED = 'mobile_disabled';

    public const POOR_ACCURACY = 'poor_accuracy';

    public const NO_LOCATION = 'no_location_configured';

    public const OUTSIDE_GEOFENCE = 'outside_geofence';

    public const FACE_REQUIRED = 'face_required';

    public const FACE_RETRY = 'face_retry';

    // Rejected or sent to review, as the policy says.
    public const MOCK_LOCATION = 'mock_location';

    public const OUTSIDE_SHIFT_WINDOW = 'outside_shift_window';

    public const FACE_FAILED = 'face_failed';

    public const FACE_NOT_ENROLLED = 'face_not_enrolled';

    // Review only.
    public const TIME_DRIFT = 'time_drift';

    public const REPEATED_ATTEMPTS = 'repeated_attempts';

    /** @var list<string> */
    public const ALL = [
        self::NOT_EMPLOYED, self::MOBILE_DISABLED, self::POOR_ACCURACY, self::NO_LOCATION, self::OUTSIDE_GEOFENCE, self::FACE_REQUIRED,
        self::FACE_RETRY, self::MOCK_LOCATION, self::OUTSIDE_SHIFT_WINDOW, self::FACE_FAILED, self::FACE_NOT_ENROLLED, self::TIME_DRIFT,
        self::REPEATED_ATTEMPTS,
    ];

    public static function label(string $code): string
    {
        return __('mobile.reasons.'.$code);
    }
}
