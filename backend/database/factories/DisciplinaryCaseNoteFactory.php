<?php

namespace Database\Factories;

use App\Enums\DisciplinaryNoteType;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryCaseNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A note on a disciplinary case.
 *
 * @extends Factory<DisciplinaryCaseNote>
 */
class DisciplinaryCaseNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'disciplinary_case_id' => DisciplinaryCase::factory(),
            'type' => DisciplinaryNoteType::Note,
            'body' => fake()->paragraph(),
        ];
    }
}
