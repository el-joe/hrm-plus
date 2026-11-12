<?php

namespace Database\Factories;

use App\Enums\AnnouncementStatus;
use App\Enums\AnnouncementTargetType;
use App\Enums\AnnouncementType;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Announcements are normally created through ManageAnnouncement (sanitised bodies, scope checks,
 * notifications); the factory writes rows directly for tests.
 *
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $number = fake()->unique()->numberBetween(1, 99999);

        return [
            'title_ar' => 'إعلان '.$number,
            'title_en' => 'Announcement '.$number,
            'body_ar' => '<p>نص الإعلان رقم '.$number.'.</p>',
            'body_en' => '<p>Text of announcement '.$number.'.</p>',
            'type' => AnnouncementType::General,
            'status' => AnnouncementStatus::Draft,
            'audience' => Announcement::AUDIENCE_ALL,
            'notify_email' => false,
            'notify_push' => true,
        ];
    }

    /**
     * Published now (or at the given time) and already notified.
     */
    public function published(?string $publishAt = null): static
    {
        return $this->state(fn (): array => [
            'status' => AnnouncementStatus::Published,
            'publish_at' => $publishAt,
            'published_at' => now(),
            'notified_at' => $publishAt === null || now()->greaterThanOrEqualTo($publishAt) ? now() : null,
        ]);
    }

    public function requiringAcknowledgement(): static
    {
        return $this->state(fn (): array => ['requires_acknowledgement' => true, 'type' => AnnouncementType::PolicyUpdate]);
    }

    /**
     * Targeted at the given records: targeting(['branch' => [1], 'department' => [4]]).
     *
     * @param  array<string, list<int>>  $targets
     */
    public function targeting(array $targets): static
    {
        return $this->state(fn (): array => ['audience' => Announcement::AUDIENCE_TARGETED])
            ->afterCreating(function (Announcement $announcement) use ($targets): void {
                foreach ($targets as $type => $ids) {
                    foreach ($ids as $id) {
                        $announcement->targets()->create(['target_type' => AnnouncementTargetType::from($type), 'target_id' => $id]);
                    }
                }
            });
    }
}
