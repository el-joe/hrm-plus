<?php

namespace App\Notifications\Disciplinary;

use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\User;
use App\Notifications\CentreNotification;

/**
 * Disciplinary events (templates "disciplinary_*"): reported / awaiting_approval / approved / rejected /
 * responded to admins holding disciplinary.view, issued to the employee (mandatory). The reason is only
 * ever given to admins.
 */
class DisciplinaryNotification extends CentreNotification
{
    public const KINDS = ['reported', 'awaiting_approval', 'approved', 'rejected', 'issued', 'responded'];

    public function __construct(
        public readonly DisciplinaryCase $case,
        public readonly string $kind,
        public readonly ?DisciplinaryAction $action = null,
        public readonly ?string $reason = null,
    ) {}

    public function event(): string
    {
        return 'disciplinary_'.$this->kind;
    }

    protected function actionLabelKey(): string
    {
        return 'disciplinary.notifications.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $case = $this->case->loadMissing(['employee' => fn ($query) => $query->withoutGlobalScopes()]);

        return [
            'employee' => $case->employee->localized('name', $locale),
            'reference' => $case->reference,
            'action' => $this->action ? __('disciplinary.enums.action_types.'.$this->action->type->value, [], $locale) : '',
            'date' => $this->action?->response_due_on?->toDateString() ?? '',
            'reason' => $this->isAdmin($notifiable) ? $this->reason : null,
        ];
    }

    /**
     * @return array{kind: string, disciplinary_case_id: int, disciplinary_action_id: ?int, reason: ?string, url: string}
     */
    public function payload(object $notifiable): array
    {
        $isAdmin = $this->isAdmin($notifiable);

        return [
            'kind' => 'disciplinary_'.$this->kind,
            'disciplinary_case_id' => $this->case->id,
            'disciplinary_action_id' => $this->action?->id,
            'reason' => $isAdmin ? $this->reason : null,
            'url' => $isAdmin || $this->action === null
                ? route('admin.disciplinary-cases.show', $this->case)
                : route('portal.disciplinary.show', $this->action->id),
        ];
    }

    private function isAdmin(object $notifiable): bool
    {
        return $notifiable instanceof User && $notifiable->isAdmin();
    }
}
