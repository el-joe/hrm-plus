<?php

namespace Database\Factories;

use App\Enums\HrRequestMessageType;
use App\Models\HrRequest;
use App\Models\HrRequestMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A reply by the employee on an HR request.
 *
 * @extends Factory<HrRequestMessage>
 */
class HrRequestMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hr_request_id' => HrRequest::factory(),
            'type' => HrRequestMessageType::Reply,
            'body' => fake()->paragraph(),
            'from_employee' => true,
        ];
    }
}
