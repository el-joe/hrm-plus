<?php

namespace App\Services\Attendance\Mobile;

use App\Enums\MobileAttemptOutcome;
use App\Http\Resources\V1\MobileAttemptResource;
use App\Models\AttendanceMobileAttempt;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

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

    /**
     * The JSON answer shared by the app API and the web portal: 201 accepted (recorded),
     * 202 flagged (sent for review, NOT recorded), 422 rejected (code "attendance_rejected",
     * data.reasons, data.face_remaining_attempts).
     */
    public function toResponse(): JsonResponse
    {
        $attempt = new MobileAttemptResource($this->attempt);

        return match ($this->attempt->outcome) {
            MobileAttemptOutcome::Accepted => ApiResponse::success($attempt, __('mobile.api.accepted_'.$this->attempt->direction->value), 201),
            MobileAttemptOutcome::Flagged => ApiResponse::success($attempt, __('mobile.api.flagged'), 202),
            MobileAttemptOutcome::Rejected => ApiResponse::error(
                __('mobile.reasons.'.($this->attempt->reasonCodes()[0] ?? 'outside_geofence')),
                422,
                'attendance_rejected',
                data: [...$attempt->resolve(), 'face_remaining_attempts' => $this->remainingFaceAttempts],
            ),
        };
    }
}
