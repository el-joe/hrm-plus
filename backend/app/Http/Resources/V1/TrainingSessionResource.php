<?php

namespace App\Http\Resources\V1;

use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A training session as the mobile app shows it (course, dates, place / link, seats left).
 *
 * @mixin TrainingSession
 */
class TrainingSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $course = $this->course;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'name' => $course->name,
                'description' => $course->description,
                'provider' => $course->provider?->name,
                'duration_hours' => (string) $course->duration_hours,
                'issues_certificate' => $course->issues_certificate,
            ],
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'start_time' => $this->start_time ? substr((string) $this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr((string) $this->end_time, 0, 5) : null,
            'delivery_mode' => $this->delivery_mode->value,
            'delivery_mode_label' => $this->delivery_mode->label(),
            'location' => $this->location,
            'meeting_url' => $this->meeting_url,
            'trainer' => $this->trainer,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'capacity' => $this->capacity,
            'seats_left' => $this->seatsLeft(),
        ];
    }
}
