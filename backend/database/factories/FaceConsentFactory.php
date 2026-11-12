<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\FaceConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An employee's active consent to face verification, given in the app.
 *
 * @extends Factory<FaceConsent>
 */
class FaceConsentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'version' => '1',
            'given_at' => now(),
            'source' => 'app',
        ];
    }
}
