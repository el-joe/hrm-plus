<?php

namespace App\Notifications\Payroll;

use App\Models\Payslip;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an employee that a payslip is available in "My payroll". The amount
 * is never included in the notification (it is shown after sign-in only).
 */
class PayslipIssuedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payslip $payslip) {}

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

        return (new MailMessage)->subject($data['title'])->line($data['message'])
            ->action(__('payroll_runs.notifications.open_payslip', [], $notifiable->locale), $data['url']);
    }

    /**
     * @return array{kind: string, payslip_id: int, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $payslip = $this->payslip->loadMissing('period');

        return [
            'kind' => 'payslip_issued',
            'payslip_id' => $payslip->id,
            'title' => __('payroll_runs.notifications.payslip_title', [], $locale),
            'message' => __('payroll_runs.notifications.payslip_message', ['period' => $payslip->period->localized('name', $locale), 'number' => $payslip->number], $locale),
            'url' => route('portal.payroll.show', $payslip->id),
        ];
    }
}
