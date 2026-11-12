<?php

namespace Database\Factories;

use App\Enums\InterviewMode;
use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Models\Interview;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An online technical interview scheduled for next week.
 *
 * @extends Factory<Interview>
 */
class InterviewFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'type' => InterviewType::Technical,
            'scheduled_at' => now()->addWeek()->setTime(10, 0),
            'timezone' => 'Africa/Cairo',
            'duration_minutes' => 60,
            'mode' => InterviewMode::Online,
            'meeting_url' => 'https://meet.example.test/'.fake()->lexify('???-????'),
            'status' => InterviewStatus::Scheduled,
        ];
    }
}
