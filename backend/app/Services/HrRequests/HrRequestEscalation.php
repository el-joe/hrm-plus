<?php

namespace App\Services\HrRequests;

use App\Models\HrRequest;
use App\Notifications\HrRequests\HrRequestNotification;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\DataScopeManager;
use Carbon\CarbonImmutable;

/**
 * SLA escalation (scheduled hourly: hr-requests:escalate). Open / in-progress requests past their due
 * time — not those waiting for the employee — move up one escalation level, at most
 * hr_requests.max_escalation_level, and again every hr_requests.escalation_repeat_hours while still
 * overdue. The escalation user of the category (else HR managers in scope) and the assignee are told
 * (event hr_request_escalated, mandatory).
 */
class HrRequestEscalation
{
    public function __construct(
        private readonly HrRequestHistory $history,
        private readonly HrRequestRecipients $recipients,
        private readonly DataScopeManager $scopes,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return int requests escalated
     */
    public function run(?CarbonImmutable $now = null): int
    {
        if (! setting('hr_requests.escalation_enabled')) {
            return 0;
        }

        $now ??= CarbonImmutable::now();
        $max = (int) setting('hr_requests.max_escalation_level');
        $repeatBefore = $now->subHours((int) setting('hr_requests.escalation_repeat_hours'));
        $count = 0;

        $this->scopes->withoutScoping(function () use ($now, $max, $repeatBefore, &$count): void {
            HrRequest::query()->withoutGlobalScopes()
                ->whereIn('status', ['open', 'in_progress'])
                ->whereNotNull('due_at')->where('due_at', '<', $now)
                ->where('escalation_level', '<', $max)
                ->where(fn ($query) => $query->whereNull('last_escalated_at')->orWhere('last_escalated_at', '<=', $repeatBefore))
                ->with(['category', 'employee' => fn ($query) => $query->withoutGlobalScopes()])
                ->orderBy('id')
                ->get()
                ->each(function (HrRequest $request) use ($now, &$count): void {
                    $level = $request->escalation_level + 1;
                    $request->forceFill(['escalation_level' => $level, 'last_escalated_at' => $now])->save();
                    $this->history->record($request, 'escalated', null, (string) ($level - 1), (string) $level);
                    $this->audit->log('hr_request.escalated', $request, meta: ['level' => $level]);

                    foreach ($this->recipients->escalationTargets($request) as $user) {
                        $user->notify(new HrRequestNotification($request, 'escalated'));
                    }

                    $count++;
                });
        });

        return $count;
    }
}
