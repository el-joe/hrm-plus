<?php

namespace App\Services\Assets;

use App\Enums\AssetEventType;
use App\Models\AssetEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\Employees\Contracts\TimelineSource;
use Illuminate\Support\Collection;

/**
 * Custody events (assigned, returned, lost) in the employee history timeline, for admins with assets.view.
 */
class AssetTimeline implements TimelineSource
{
    public function entries(User $user, Employee $employee): Collection
    {
        if (! $user->can('assets.view')) {
            return collect();
        }

        return AssetEvent::query()->where('employee_id', $employee->id)
            ->whereIn('type', [AssetEventType::Assigned, AssetEventType::Returned, AssetEventType::MarkedLost])
            ->with(['asset' => fn ($query) => $query->withoutGlobalScopes()])
            ->get()
            ->map(fn (AssetEvent $event): array => [
                'date' => $event->occurred_on,
                'kind' => 'event',
                'id' => $event->id,
                'module' => 'assets',
                'title' => __('assets.timeline.'.$event->type->value, ['asset' => $event->asset?->label()]),
                'description' => $event->note,
                'url' => $event->asset && $user->can('view', $event->asset) ? route('admin.assets.show', $event->asset) : null,
                'tone' => $event->type->tone(),
                'badge' => $event->condition?->label(),
            ]);
    }
}
