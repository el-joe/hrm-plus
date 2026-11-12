<?php

namespace Database\Factories;

use App\Models\LeaveRequest;
use App\Models\LeaveRequestAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A PDF attached to a leave request.
 *
 * @extends Factory<LeaveRequestAttachment>
 */
class LeaveRequestAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_request_id' => LeaveRequest::factory(),
            'file_path' => 'leave-requests/'.fake()->uuid().'.pdf',
            'file_name' => 'medical-report.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 500_000),
        ];
    }
}
