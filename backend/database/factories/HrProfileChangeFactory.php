<?php

namespace Database\Factories;

use App\Enums\HrProfileChangeStatus;
use App\Models\HrProfileChange;
use App\Models\HrRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending phone number change carried by an HR request.
 *
 * @extends Factory<HrProfileChange>
 */
class HrProfileChangeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hr_request_id' => HrRequest::factory(),
            'field' => 'phone',
            'old_value' => '+2010'.fake()->numerify('########'),
            'new_value' => '+2012'.fake()->numerify('########'),
            'status' => HrProfileChangeStatus::Pending,
        ];
    }
}
