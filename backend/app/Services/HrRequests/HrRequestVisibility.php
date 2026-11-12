<?php

namespace App\Services\HrRequests;

use App\Enums\HrRequestStatus;
use App\Models\HrRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which HR requests an admin sees (on top of the data scope, applied by the global scope in the admin area):
 *
 * - never a request about themselves;
 * - hr_requests.manage: every request in scope;
 * - otherwise (hr_requests.view): requests assigned to them, unassigned requests, and — with
 *   hr_requests.approve — requests waiting for an approval decision.
 */
class HrRequestVisibility
{
    /**
     * @return Builder<HrRequest>
     */
    public function query(User $user): Builder
    {
        $query = HrRequest::query();
        $this->constrain($query, $user);

        return $query;
    }

    /**
     * @param  Builder<HrRequest>  $query
     */
    public function constrain(Builder $query, User $user): void
    {
        if ($user->employee_id !== null) {
            $query->where($query->qualifyColumn('employee_id'), '!=', $user->employee_id);
        }

        if ($user->can('hr_requests.manage')) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where($query->qualifyColumn('assigned_to'), $user->id)
                ->orWhereNull($query->qualifyColumn('assigned_to'));

            if ($user->can('hr_requests.approve')) {
                $query->orWhere($query->qualifyColumn('status'), HrRequestStatus::PendingApproval->value);
            }
        });
    }

    public function canSee(User $user, HrRequest $request): bool
    {
        if (! $user->isAdmin() || ! $user->can('hr_requests.view') || ($user->employee_id !== null && $user->employee_id === $request->employee_id)) {
            return false;
        }

        if (! $request->isVisibleTo($user)) {
            return false;
        }

        return $user->can('hr_requests.manage')
            || $request->assigned_to === null
            || $request->assigned_to === $user->id
            || ($request->status === HrRequestStatus::PendingApproval && $user->can('hr_requests.approve'));
    }
}
