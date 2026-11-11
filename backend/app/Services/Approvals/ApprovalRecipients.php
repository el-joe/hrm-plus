<?php

namespace App\Services\Approvals;

use App\Enums\ApproverType;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Support\Collection;

/**
 * The admin accounts that may act on the current step of a pending request
 * (for "awaiting your approval" notifications): the step's user, the
 * holders of its role or of its permission, filtered by
 * ApprovalService::canAct (active admins, data scope, no self-approval).
 */
class ApprovalRecipients
{
    public function __construct(private readonly ApprovalService $approvals, private readonly DataScopeManager $scopes) {}

    /**
     * @return Collection<int, User>
     */
    public function for(ApprovalRequest $request): Collection
    {
        $step = $request->currentStep;

        if (! $request->isPending() || $step === null) {
            return collect();
        }

        $candidates = match ($step->approver_type) {
            ApproverType::User => User::query()->whereKey($step->approver_user_id)->get(),
            ApproverType::Role => User::query()->admins()->whereHas('roles', fn ($query) => $query->whereKey($step->approver_role_id))->get(),
            ApproverType::Permission => User::query()->admins()->where(fn ($query) => $query
                ->whereHas('roles.permissions', fn ($permission) => $permission->where('name', $step->approver_permission))
                ->orWhereHas('permissions', fn ($permission) => $permission->where('name', $step->approver_permission)))->get(),
        };

        return $this->scopes->withoutScoping(fn (): Collection => $candidates
            ->filter(fn (User $user): bool => $this->approvals->canAct($request, $user))
            ->values());
    }
}
