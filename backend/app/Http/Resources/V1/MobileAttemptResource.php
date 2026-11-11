<?php

namespace App\Http\Resources\V1;

use App\Models\AttendanceMobileAttempt;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\Mobile\AttemptReason;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A mobile check-in / check-out attempt and the server's decision.
 *
 * outcome: accepted (recorded) | flagged (waiting for review: NOT recorded yet) | rejected
 *
 * @mixin AttendanceMobileAttempt
 */
class MobileAttemptResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $face = $this->relationLoaded('faceVerification') ? $this->faceVerification : null;

        return [
            'request_uuid' => $this->request_uuid,
            'direction' => $this->direction->value,
            'outcome' => $this->outcome->value,
            'review_status' => $this->review_status?->value,
            'recorded' => $this->attendance_raw_event_id !== null,
            'server_time' => $this->server_time->toIso8601ZuluString(),
            'server_time_local' => AttendanceClock::localTime($this->server_time, $this->timezone),
            'work_date' => $this->work_date?->toDateString(),
            'reasons' => array_map(fn (string $code): array => ['code' => $code, 'message' => AttemptReason::label($code)], $this->reasonCodes()),
            'location' => [
                'id' => $this->branch_location_id,
                'name' => $this->location?->name,
                'distance_meters' => $this->distance_meters,
                'allowed_radius_meters' => $this->allowed_radius_meters,
                'within_geofence' => $this->within_geofence,
                'allow_anywhere' => $this->allow_anywhere,
                'accuracy_meters' => (float) $this->accuracy_meters,
            ],
            'within_shift_window' => $this->within_shift_window,
            'time_drift_seconds' => $this->time_drift_seconds,
            'face' => [
                'mode' => $this->face_mode->value,
                'result' => $face?->result->value,
                'is_simulated' => $face?->is_simulated,
            ],
        ];
    }
}
