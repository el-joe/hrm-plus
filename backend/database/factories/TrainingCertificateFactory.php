<?php

namespace Database\Factories;

use App\Enums\CertificateType;
use App\Models\Employee;
use App\Models\TrainingCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A certificate without a file, issued last year, valid two years.
 *
 * @extends Factory<TrainingCertificate>
 */
class TrainingCertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'type' => CertificateType::Certificate,
            'name' => 'Certificate '.fake()->unique()->numberBetween(1, 99999),
            'issuer' => 'Provider',
            'issued_on' => now()->subYear()->toDateString(),
            'expires_on' => now()->addYear()->toDateString(),
        ];
    }

    public function expiringIn(int $days): static
    {
        return $this->state(fn (array $attributes) => ['expires_on' => now()->addDays($days)->toDateString()]);
    }
}
