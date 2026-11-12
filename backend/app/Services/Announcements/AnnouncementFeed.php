<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * An employee's announcements (portal, API, portal home card): live announcements addressed to them,
 * pinned first then newest, with their own read / acknowledgement row eager-loaded as "myRead".
 */
class AnnouncementFeed
{
    /**
     * @return Builder<Announcement>
     */
    public function forEmployee(Employee $employee, bool $unreadOnly = false): Builder
    {
        return Announcement::query()->visibleTo($employee)
            ->when($unreadOnly, fn (Builder $query) => $query->whereDoesntHave('reads', fn (Builder $read) => $read->where('employee_id', $employee->id)))
            ->with([
                'reads' => fn ($read) => $read->where('employee_id', $employee->id),
                'attachments',
            ])
            ->withCount('attachments')
            ->feedOrder();
    }

    /**
     * @return Collection<int, Announcement>
     */
    public function latest(Employee $employee, int $limit = 3): Collection
    {
        return $this->forEmployee($employee)->limit($limit)->get();
    }

    public function unreadCount(Employee $employee): int
    {
        return Announcement::query()->visibleTo($employee)
            ->whereDoesntHave('reads', fn (Builder $read) => $read->where('employee_id', $employee->id))->count();
    }

    /**
     * Announcements that still wait for the employee's acknowledgement.
     */
    public function pendingAcknowledgements(Employee $employee): int
    {
        return Announcement::query()->visibleTo($employee)->where('requires_acknowledgement', true)
            ->whereDoesntHave('reads', fn (Builder $read) => $read->where('employee_id', $employee->id)->whereNotNull('acknowledged_at'))->count();
    }

    public function find(Employee $employee, int $id): ?Announcement
    {
        return $this->forEmployee($employee)->whereKey($id)->first();
    }

    /**
     * The employee's read row of an announcement loaded by forEmployee() (null when unread).
     */
    public static function readOf(Announcement $announcement): ?AnnouncementRead
    {
        return $announcement->relationLoaded('reads') ? $announcement->reads->first() : null;
    }
}
