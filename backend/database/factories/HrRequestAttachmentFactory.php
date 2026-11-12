<?php

namespace Database\Factories;

use App\Models\HrRequest;
use App\Models\HrRequestAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A PDF attached to an HR request by the employee.
 *
 * @extends Factory<HrRequestAttachment>
 */
class HrRequestAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hr_request_id' => HrRequest::factory(),
            'file_path' => 'hr-requests/'.fake()->uuid().'.pdf',
            'file_name' => 'document.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 500_000),
        ];
    }
}
