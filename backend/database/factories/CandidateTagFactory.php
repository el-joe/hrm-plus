<?php

namespace Database\Factories;

use App\Models\CandidateTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A free-form candidate label.
 *
 * @extends Factory<CandidateTag>
 */
class CandidateTagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'tag '.fake()->unique()->numberBetween(1, 999_999),
        ];
    }
}
