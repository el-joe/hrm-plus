<?php

namespace Database\Factories;

use App\Enums\AttendanceStatusCategory;
use App\Models\AttendanceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A custom (non-system) paid status counted as present.
 *
 * @extends Factory<AttendanceStatus>
 */
class AttendanceStatusFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(100, 99999);

        return [
            'code' => 'ST'.$number,
            'name_ar' => 'حالة '.$number,
            'name_en' => 'Status '.$number,
            'category' => AttendanceStatusCategory::Present,
            'counts_as_worked' => true,
            'is_paid' => true,
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
