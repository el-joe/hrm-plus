<?php

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\Announcement;
use App\Models\AnnouncementTarget;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Targets an announcement at one branch.
 *
 * @extends Factory<AnnouncementTarget>
 */
class AnnouncementTargetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'target_type' => AnnouncementTargetType::Branch,
            'target_id' => Branch::factory(),
        ];
    }
}
