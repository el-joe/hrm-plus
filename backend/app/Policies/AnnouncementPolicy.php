<?php

namespace App\Policies;

use App\Actions\Announcements\ManageAnnouncement;
use App\Models\Announcement;
use App\Models\User;

/**
 * Announcements in the admin area.
 *
 * - view: announcements.view, and for an admin with data restrictions only company-wide announcements,
 *   their own, and those targeting their scope (Announcement::scopeVisibleToAdmin);
 * - update / publish / archive / pin: announcements.update on an announcement the admin may address
 *   (restricted admins: their own drafts / targeted announcements entirely inside their scope);
 * - delete: announcements.delete, drafts only;
 * - export (read report): announcements.export.
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() && $user->can('announcements.view');
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return $this->viewAny($user) && Announcement::query()->whereKey($announcement->id)->visibleToAdmin($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() && $user->can('announcements.create');
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->can('announcements.update') && ! $announcement->isArchived() && $this->manages($user, $announcement);
    }

    public function archive(User $user, Announcement $announcement): bool
    {
        return $user->can('announcements.update') && $this->manages($user, $announcement);
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->can('announcements.delete') && $announcement->isDraft() && $this->manages($user, $announcement);
    }

    public function export(User $user, Announcement $announcement): bool
    {
        return $user->can('announcements.export') && $this->view($user, $announcement);
    }

    private function manages(User $user, Announcement $announcement): bool
    {
        if (! $this->view($user, $announcement)) {
            return false;
        }

        $announcement->loadMissing('targets');

        return app(ManageAnnouncement::class)->canTarget($user, $announcement->audience, ManageAnnouncement::targetMap($announcement));
    }
}
