<?php

namespace App\Services\HrRequests;

use App\Models\HrRequest;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Support\Collection;

/**
 * Who is told about an HR request:
 *
 * - handlers(): the assignee; else the category's default assignee; else every active admin holding
 *   hr_requests.update who may see the request;
 * - escalationTargets(): the category's escalation user, else active admins with hr_requests.manage
 *   who may see it — plus the assignee;
 * - employeeUsers(): the employee's own active account(s).
 *
 * The employee concerned is never an admin recipient.
 */
class HrRequestRecipients
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    /**
     * @return Collection<int, User>
     */
    public function handlers(HrRequest $request): Collection
    {
        foreach ([$request->assigned_to, $request->category?->default_assignee_id] as $id) {
            $user = $id !== null ? User::query()->admins()->find($id) : null;

            if ($user !== null && $user->isActive() && $user->employee_id !== $request->employee_id) {
                return collect([$user]);
            }
        }

        return $this->holders($request, 'hr_requests.update');
    }

    /**
     * @return Collection<int, User>
     */
    public function escalationTargets(HrRequest $request): Collection
    {
        $targets = collect();
        $named = $request->category?->escalation_user_id !== null ? User::query()->admins()->find($request->category->escalation_user_id) : null;

        if ($named !== null && $named->isActive() && $named->employee_id !== $request->employee_id) {
            $targets->push($named);
        } else {
            $targets = $this->holders($request, 'hr_requests.manage');
        }

        $assignee = $request->assigned_to !== null ? User::query()->find($request->assigned_to) : null;

        if ($assignee !== null && $assignee->isActive()) {
            $targets->push($assignee);
        }

        return $targets->unique('id')->values();
    }

    /**
     * @return Collection<int, User>
     */
    public function employeeUsers(HrRequest $request): Collection
    {
        return User::query()->where('employee_id', $request->employee_id)->get()->filter(fn (User $user): bool => $user->isActive())->values();
    }

    /**
     * Active admins holding the permission whose data scope (and request visibility) covers the request.
     *
     * @return Collection<int, User>
     */
    public function holders(HrRequest $request, string $permission): Collection
    {
        $candidates = User::query()->admins()->where(fn ($query) => $query
            ->whereHas('roles.permissions', fn ($row) => $row->where('name', $permission))
            ->orWhereHas('permissions', fn ($row) => $row->where('name', $permission)))->get();

        return $this->scopes->withoutScoping(fn (): Collection => $candidates
            ->filter(fn (User $user): bool => $user->isActive()
                && $user->employee_id !== $request->employee_id
                && $request->isVisibleTo($user))
            ->values());
    }
}
