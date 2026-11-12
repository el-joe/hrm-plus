<?php

namespace Database\Factories;

use App\Models\SalaryStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active structure without versions (see salaryStructure() for a complete one).
 *
 * @extends Factory<SalaryStructure>
 */
class SalaryStructureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 9_999_999);

        return [
            'code' => 'SS'.$number,
            'name_ar' => 'هيكل '.$number,
            'name_en' => 'Structure '.$number,
            'is_active' => true,
        ];
    }
}
