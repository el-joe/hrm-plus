<?php

namespace Database\Factories;

use App\Enums\TrainingDeliveryMode;
use App\Enums\TrainingSessionStatus;
use App\Models\TrainingCourse;
use App\Models\TrainingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A scheduled two-day classroom session two weeks ahead, 10 seats.
 *
 * @extends Factory<TrainingSession>
 */
class TrainingSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDays(14)->startOfDay();

        return [
            'reference' => 'TRS-T-'.fake()->unique()->numberBetween(1, 9_999_999),
            'training_course_id' => TrainingCourse::factory(),
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDay()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '16:00',
            'delivery_mode' => TrainingDeliveryMode::Classroom,
            'location' => 'Training room A',
            'capacity' => 10,
            'status' => TrainingSessionStatus::Scheduled,
        ];
    }

    /**
     * A session held on the given days (Y-m-d).
     */
    public function on(string $start, ?string $end = null): static
    {
        return $this->state(fn (array $attributes) => ['start_date' => $start, 'end_date' => $end ?? $start]);
    }
}
