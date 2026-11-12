<?php

namespace App\Notifications\Disciplinary;

use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Disciplinary events, database + mail (mail when setting notifications.email_enabled).
 * Messages carry the reference and the kind of action only — never the incident
 * description, findings or the employee's response (restricted data).
 *
 * - to disciplinary viewers in scope: reported (a new incident), responded (the employee answered);
 * - to approvers: awaiting_approval;
 * - to the proposer: approved, rejected;
 * - to the employee: issued (portal "Disciplinary notices").
 */
class DisciplinaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KINDS = ['reported', 'awaiting_approval', 'approved', 'rejected', 'issued', 'responded'];

    public function __construct(
        public readonly DisciplinaryCase $case,
        public readonly string $kind,
        public readonly ?DisciplinaryAction $action = null,
        public readonly ?string $reason = null,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return setting('notifications.email_enabled') ? ['database', 'mail'] : ['database'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);
        $mail = (new MailMessage)->subject($data['title'])->line($data['message']);

        if ($this->reason && $notifiable->isAdmin()) {
            $mail->line(__('disciplinary.notifications.reason', ['reason' => $this->reason], $notifiable->locale));
        }

        return $mail->action(__('disciplinary.notifications.open', [], $notifiable->locale), $data['url']);
    }

    /**
     * @return array{kind: string, disciplinary_case_id: int, disciplinary_action_id: ?int, title: string, message: string, reason: ?string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $case = $this->case->loadMissing(['employee' => fn ($query) => $query->withoutGlobalScopes()]);
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin();

        return [
            'kind' => 'disciplinary_'.$this->kind,
            'disciplinary_case_id' => $case->id,
            'disciplinary_action_id' => $this->action?->id,
            'title' => __('disciplinary.notifications.'.$this->kind.'_title', [], $locale),
            'message' => __('disciplinary.notifications.'.$this->kind.'_message', [
                'employee' => $case->employee->localized('name', $locale),
                'reference' => $case->reference,
                'action' => $this->action ? __('disciplinary.enums.action_types.'.$this->action->type->value, [], $locale) : '',
                'date' => $this->action?->response_due_on?->toDateString() ?? '',
            ], $locale),
            'reason' => $isAdmin ? $this->reason : null,
            'url' => $isAdmin || $this->action === null
                ? route('admin.disciplinary-cases.show', $case)
                : route('portal.disciplinary.show', $this->action->id),
        ];
    }
}
