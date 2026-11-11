<?php

namespace App\Services\Attendance\Mobile;

use App\Enums\MobileAttemptOutcome;
use App\Models\AttendanceMobileAttempt;

/**
 * What MobileCheckIn decided: the stored attempt and, for face retries, the attempts left.
 */
final readonly class CheckInResult
{
    public function __construct(
        public AttendanceMobileAttempt $attempt,
        public ?int $remainingFaceAttempts = null,
        public bool $replayed = false,
    ) {}

    public function httpStatus(): int
    {
        return match ($this->attempt->outcome) {
            MobileAttemptOutcome::Accepted => 201,
            MobileAttemptOutcome::Flagged => 202,
            MobileAttemptOutcome::Rejected => 422,
        };
    }
}
