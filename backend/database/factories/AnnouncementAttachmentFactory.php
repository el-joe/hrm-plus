<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A PDF attached to an announcement.
 *
 * @extends Factory<AnnouncementAttachment>
 */
class AnnouncementAttachmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'file_path' => 'announcements/'.fake()->uuid().'.pdf',
            'file_name' => fake()->word().'.pdf',
            'file_mime' => 'application/pdf',
            'file_size' => fake()->numberBetween(10_000, 500_000),
        ];
    }
}
