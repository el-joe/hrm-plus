<?php

namespace App\Services\HrRequests;

use App\Enums\HrRequestStatus;
use App\Models\Employee;
use App\Models\HrRequestEvent;
use App\Models\User;
use App\Services\Employees\Contracts\TimelineSource;
use Illuminate\Support\Collection;

/**
 * Employee History tab: applied profile change requests and issued letters (Phase 8b), for admins who may
 * view HR requests. Values themselves are not shown here (the request page masks them).
 */
class HrRequestTimeline implements TimelineSource
{
    public function entries(User $user, Employee $employee): Collection
    {
        if (! $user->can('hr_requests.view') || $user->employee_id === $employee->id) {
            return collect();
        }

        return HrRequestEvent::query()->whereIn('type', ['changes_applied', 'letter_issued'])
            ->whereHas('hrRequest', fn ($query) => $query->withoutGlobalScopes()->where('employee_id', $employee->id))
            ->with(['hrRequest' => fn ($query) => $query->withoutGlobalScopes()->with('category')])
            ->get()
            ->map(fn (HrRequestEvent $event): array => [
                'date' => $event->created_at,
                'kind' => 'event',
                'id' => $event->id,
                'module' => 'hr_requests',
                'title' => __('hr_requests.history.'.$event->type),
                'description' => $event->hrRequest->reference.' · '.$event->hrRequest->category->name,
                'url' => route('admin.hr-requests.show', $event->hr_request_id),
                'tone' => $event->type === 'changes_applied' ? 'info' : 'success',
                'badge' => $event->hrRequest->status === HrRequestStatus::Resolved ? null : $event->hrRequest->status->label(),
            ])->toBase()->values();
    }
}
