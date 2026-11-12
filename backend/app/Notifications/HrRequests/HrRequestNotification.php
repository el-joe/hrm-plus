<?php

namespace App\Notifications\HrRequests;

use App\Models\HrRequest;
use App\Models\User;
use App\Notifications\CentreNotification;
use Illuminate\Support\Str;

/**
 * HR request events (texts: notification templates "hr_request_*"):
 *
 * - to the employee: received, reply (an employee-visible HR reply), status (resolved / rejected /
 *   cancelled / approved / awaiting the employee);
 * - to HR: submitted, assigned, awaiting_approval, employee_reply, escalated (mandatory).
 *
 * $excerpt is only ever an employee-visible text (a reply), never an internal note.
 * Admin recipients get the admin request page, employees the portal "My requests".
 */
class HrRequestNotification extends CentreNotification
{
    public const KINDS = ['received', 'submitted', 'assigned', 'awaiting_approval', 'reply', 'employee_reply', 'status', 'escalated'];

    public function __construct(
        public readonly HrRequest $hrRequest,
        public readonly string $kind,
        public readonly ?string $excerpt = null,
        public readonly ?string $reason = null,
        public readonly ?string $authorName = null,
    ) {}

    public function event(): string
    {
        return 'hr_request_'.$this->kind;
    }

    protected function actionLabelKey(): string
    {
        return 'hr_requests.notifications.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $request = $this->request();

        return [
            'reference' => $request->reference,
            'subject' => $request->subject,
            'category' => $request->category->localized('name', $locale),
            'employee' => $request->employee->localized('name', $locale),
            'priority' => __('hr_requests.enums.priorities.'.$request->priority->value, [], $locale),
            'status' => __('hr_requests.enums.statuses.'.$request->status->value, [], $locale),
            'due' => $request->due_at?->timezone((string) setting('localisation.timezone'))->format('Y-m-d H:i') ?? '—',
            'level' => $request->escalation_level,
            'assignee' => $request->assignee?->name ?? __('hr_requests.common.unassigned', [], $locale),
            'author' => $this->authorName ?? '',
            'excerpt' => $this->excerpt !== null ? Str::limit(trim($this->excerpt), 140) : '',
            'reason' => $this->reason,
        ];
    }

    /**
     * @return array{kind: string, hr_request_id: int, reference: string, reason: ?string, url: string}
     */
    public function payload(object $notifiable): array
    {
        $forEmployee = ! ($notifiable instanceof User && $notifiable->isAdmin() && $notifiable->employee_id !== $this->hrRequest->employee_id);

        return [
            'kind' => 'hr_request_'.$this->kind,
            'hr_request_id' => $this->hrRequest->id,
            'reference' => $this->hrRequest->reference,
            'reason' => $this->reason,
            'url' => $forEmployee ? route('portal.requests.show', $this->hrRequest->id) : route('admin.hr-requests.show', $this->hrRequest),
        ];
    }

    private function request(): HrRequest
    {
        return $this->hrRequest->loadMissing([
            'employee' => fn ($query) => $query->withoutGlobalScopes(),
            'category', 'assignee',
        ]);
    }
}
