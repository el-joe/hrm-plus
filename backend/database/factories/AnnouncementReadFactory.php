<?php

namespace Database\Factories;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An employee has read (not acknowledged) an announcement.
 *
 * @extends Factory<AnnouncementRead>
 */
class AnnouncementReadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'announcement_id' => Announcement::factory(),
            'employee_id' => Employee::factory(),
            'read_at' => now(),
        ];
    }
}
