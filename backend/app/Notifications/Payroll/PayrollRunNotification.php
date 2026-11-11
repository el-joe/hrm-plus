<?php

namespace App\Notifications\Payroll;

use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payroll run events for admins, database + mail (mail when setting
 * notifications.email_enabled): awaiting_approval (approvers of the current
 * step), approved / rejected (the submitter), finalised (the submitter and
 * creator). No amount appears in any message.
 */
class PayrollRunNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KINDS = ['awaiting_approval', 'approved', 'rejected', 'finalised'];

    public function __construct(public readonly PayrollRun $run, public readonly string $kind, public readonly ?string $reason = null) {}

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

        if ($this->reason) {
            $mail->line(__('payroll_runs.notifications.reason', ['reason' => $this->reason], $notifiable->locale));
        }

        return $mail->action(__('payroll_runs.notifications.open', [], $notifiable->locale), $data['url']);
    }

    /**
     * @return array{kind: string, payroll_run_id: int, title: string, message: string, reason: ?string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $run = $this->run->loadMissing('period');

        return [
            'kind' => 'payroll_run_'.$this->kind,
            'payroll_run_id' => $run->id,
            'title' => __('payroll_runs.notifications.'.$this->kind.'_title', ['reference' => $run->reference], $locale),
            'message' => __('payroll_runs.notifications.'.$this->kind.'_message', [
                'reference' => $run->reference, 'name' => $run->name, 'period' => $run->period->localized('name', $locale),
            ], $locale),
            'reason' => $this->reason,
            'url' => route('admin.payroll-runs.show', $run),
        ];
    }
}
