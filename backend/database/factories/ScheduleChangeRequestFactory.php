<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Enums\ScheduleChangeType;
use App\Models\Employee;
use App\Models\ScheduleChangeRequest;
use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending request to work another shift next week.
 *
 * @extends Factory<ScheduleChangeRequest>
 */
class ScheduleChangeRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ScheduleChangeType::Change,
            'employee_id' => Employee::factory(),
            'work_date' => now()->addWeek()->toDateString(),
            'shift_id' => Shift::factory(),
            'reason' => fake()->sentence(),
            'status' => RequestStatus::Pending,
        ];
    }
}
