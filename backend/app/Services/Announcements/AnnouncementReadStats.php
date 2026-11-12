<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\AnnouncementRead;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read / acknowledgement statistics of an announcement against its CURRENT audience (active employees
 * matching the targets today). Reads by employees who left the audience are not counted.
 */
class AnnouncementReadStats
{
    public function __construct(private readonly AnnouncementAudience $audience) {}

    /**
     * @return array{audience: int, read: int, unread: int, acknowledged: int, pending_acknowledgement: int, read_percent: float, acknowledged_percent: float}
     */
    public function summary(Announcement $announcement): array
    {
        $audience = $this->audience->count($announcement);
        $reads = AnnouncementRead::query()->where('announcement_id', $announcement->id)
            ->whereIn('employee_id', $this->audience->employees($announcement)->select('employees.id'));
        $read = (clone $reads)->count();
        $acknowledged = (clone $reads)->whereNotNull('acknowledged_at')->count();

        return [
            'audience' => $audience,
            'read' => $read,
            'unread' => max(0, $audience - $read),
            'acknowledged' => $acknowledged,
            'pending_acknowledgement' => $announcement->requires_acknowledgement ? max(0, $audience - $acknowledged) : 0,
            'read_percent' => $audience > 0 ? round($read * 100 / $audience, 1) : 0.0,
            'acknowledged_percent' => $audience > 0 ? round($acknowledged * 100 / $audience, 1) : 0.0,
        ];
    }

    /**
     * Audience employees, optionally filtered by read / acknowledgement state (list and export).
     * Their read rows come from readsFor().
     *
     * @param  'all'|'read'|'unread'|'acknowledged'|'not_acknowledged'|null  $filter
     * @return Builder<Employee>
     */
    public function recipients(Announcement $announcement, ?string $filter = null, ?string $search = null): Builder
    {
        $read = fn (bool $acknowledged = false) => fn ($query) => $query->selectRaw('1')->from('announcement_reads')
            ->whereColumn('announcement_reads.employee_id', 'employees.id')
            ->where('announcement_reads.announcement_id', $announcement->id)
            ->when($acknowledged, fn ($query) => $query->whereNotNull('announcement_reads.acknowledged_at'));

        return $this->audience->employees($announcement)
            ->with([
                'department' => fn ($query) => $query->withoutGlobalScopes(),
                'branch' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->when($search, fn (Builder $query, string $term) => $query->search($term))
            ->when($filter === 'read', fn (Builder $query) => $query->whereExists($read()))
            ->when($filter === 'unread', fn (Builder $query) => $query->whereNotExists($read()))
            ->when($filter === 'acknowledged', fn (Builder $query) => $query->whereExists($read(true)))
            ->when($filter === 'not_acknowledged', fn (Builder $query) => $query->whereNotExists($read(true)))
            ->orderBy('employees.employee_number');
    }

    /**
     * Read rows of the given employees, keyed by employee id.
     *
     * @param  iterable<Employee>|list<int>  $employees
     * @return Collection<int, AnnouncementRead>
     */
    public function readsFor(Announcement $announcement, iterable $employees): Collection
    {
        $ids = collect($employees)->map(fn ($employee): int => $employee instanceof Employee ? $employee->id : (int) $employee)->all();

        return AnnouncementRead::query()->where('announcement_id', $announcement->id)->whereIn('employee_id', $ids ?: [0])
            ->get()->keyBy('employee_id');
    }
}
