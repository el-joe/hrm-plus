<?php

namespace Database\Factories;

use App\Models\HrRequest;
use App\Models\HrRequestEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An internal status change in an HR request's timeline.
 *
 * @extends Factory<HrRequestEvent>
 */
class HrRequestEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hr_request_id' => HrRequest::factory(),
            'type' => 'status_changed',
            'from_value' => 'open',
            'to_value' => 'in_progress',
            'visible_to_employee' => false,
        ];
    }
}
