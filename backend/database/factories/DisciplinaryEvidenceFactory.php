<?php

namespace Database\Factories;

use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryEvidence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A PDF attached to a disciplinary case.
 *
 * @extends Factory<DisciplinaryEvidence>
 */
class DisciplinaryEvidenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disciplinary_case_id' => DisciplinaryCase::factory(),
            'file_path' => 'disciplinary/'.fake()->uuid().'.pdf',
            'file_name' => 'evidence.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 500_000),
        ];
    }
}
