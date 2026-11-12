<?php

namespace Database\Factories;

use App\Enums\DisciplinaryActionType;
use App\Enums\ViolationSeverity;
use App\Models\ViolationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active minor violation whose first offence earns a verbal warning.
 *
 * @extends Factory<ViolationType>
 */
class ViolationTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'VT'.$number,
            'name_ar' => 'مخالفة '.$number,
            'name_en' => 'Violation '.$number,
            'severity' => ViolationSeverity::Minor,
            'default_action' => DisciplinaryActionType::VerbalWarning,
            'is_active' => true,
        ];
    }
}
