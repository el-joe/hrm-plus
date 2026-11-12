<?php

namespace Database\Factories;

use App\Models\AttendancePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active attendance policy without versions (see AttendancePolicyVersionFactory).
 *
 * @extends Factory<AttendancePolicy>
 */
class AttendancePolicyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'AP'.$number,
            'name_ar' => 'سياسة '.$number,
            'name_en' => 'Policy '.$number,
            'is_active' => true,
        ];
    }
}
