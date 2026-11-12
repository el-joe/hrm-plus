<?php

namespace Database\Factories;

use App\Models\PayEntry;
use App\Models\PayEntryAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A PDF attached to an allowance or deduction.
 *
 * @extends Factory<PayEntryAttachment>
 */
class PayEntryAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pay_entry_id' => PayEntry::factory(),
            'file_path' => 'pay-entries/'.fake()->uuid().'.pdf',
            'file_name' => 'approval.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 500_000),
        ];
    }
}
