<?php

namespace App\Services\Notifications\Automation;

use App\Enums\UserStatus;
use App\Models\ApprovalRequest;
use App\Models\User;
use App\Notifications\Automation\AutomationNotification;
use App\Services\Approvals\ApprovalService;
use Carbon\CarbonImmutable;

/**
 * Pending approvals reminder (approvals_digest, notifications:approvals-digest on working days): every
 * active admin who can act on approval requests waiting longer than notifications.approvals_reminder_hours
 * gets one digest per day with the count, the oldest date and a per-workflow summary. Requests outside the
 * admin's data scope are not counted (ApprovalService::canAct re-checks scope).
 */
class ApprovalsDigest
{
    public function __construct(private readonly ApprovalService $approvals, private readonly NotificationDispatches $dispatches) {}

    public function run(?CarbonImmutable $now = null): int
    {
        if (! setting('notifications.approvals_digest_enabled')) {
            return 0;
        }

        $now ??= CarbonImmutable::now();
        $before = $now->subHours((int) setting('notifications.approvals_reminder_hours'));
        $sent = 0;

        User::query()->admins()->where('status', UserStatus::Active)->orderBy('id')->each(function (User $admin) use ($before, $now, &$sent): void {
            $waiting = $this->approvals->pendingFor($admin)->where('created_at', '<=', $before)
                ->with(['workflow', 'currentStep'])->orderBy('created_at')->get()
                ->filter(fn (ApprovalRequest $request): bool => $this->approvals->canAct($request, $admin))->values();

            if ($waiting->isEmpty()) {
                return;
            }

            $summary = $waiting->groupBy(fn (ApprovalRequest $request): string => (string) $request->workflow?->name)
                ->map(fn ($group, string $name): string => $name.' ('.$group->count().')')->implode(', ');

            $sent += (int) $this->dispatches->once('approvals_digest:'.$now->toDateString().':'.$admin->id, 'approvals_digest', function () use ($admin, $waiting, $summary): int {
                $admin->notify(new AutomationNotification('approvals_digest', [
                    'count' => $waiting->count(),
                    'date' => $waiting->first()->created_at->toDateString(),
                    'summary' => $summary,
                ], ['url' => route('admin.dashboard')]));

                return 1;
            });
        });

        return $sent;
    }
}
