<?php

namespace App\Notifications\Payroll;

use App\Models\EmployeeLoan;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Loan / advance events, database + mail (mail when setting notifications.email_enabled):
 *
 * - to approvers: awaiting_approval, change_awaiting_approval;
 * - to the employee: submitted, approved, rejected, change_approved, change_rejected.
 *
 * Admins get the admin loan page, employees the portal "My loans". Amounts
 * appear only in the in-app message, never in the e-mail subject.
 */
class LoanNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const KINDS = ['submitted', 'awaiting_approval', 'approved', 'rejected', 'change_awaiting_approval', 'change_approved', 'change_rejected'];

    public function __construct(public readonly EmployeeLoan $loan, public readonly string $kind, public readonly ?string $reason = null) {}

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
            $mail->line(__('payroll.loans.notifications.reason', ['reason' => $this->reason], $notifiable->locale));
        }

        return $mail->action(__('payroll.loans.notifications.open', [], $notifiable->locale), $data['url']);
    }

    /**
     * @return array{kind: string, employee_loan_id: int, title: string, message: string, reason: ?string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $locale = $notifiable instanceof User ? $notifiable->locale : null;
        $loan = $this->loan->loadMissing(['employee' => fn ($query) => $query->withoutGlobalScopes()]);
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin();

        return [
            'kind' => 'loan_'.$this->kind,
            'employee_loan_id' => $loan->id,
            'title' => __('payroll.loans.notifications.'.$this->kind.'_title', ['type' => $loan->type->label()], $locale),
            'message' => __('payroll.loans.notifications.'.$this->kind.'_message', [
                'employee' => $loan->employee->localized('name', $locale),
                'reference' => $loan->reference,
                'amount' => Money::format((string) ($loan->approved_amount ?? $loan->requested_amount)),
            ], $locale),
            'reason' => $this->reason,
            'url' => $isAdmin ? route('admin.loans.show', $loan) : route('portal.loans.show', $loan->id),
        ];
    }
}
