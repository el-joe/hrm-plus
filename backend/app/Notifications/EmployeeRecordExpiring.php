<?php

namespace App\Notifications;

use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "A document / contract expires on <date>". Stored in the database channel
 * (the notification centre of a later phase lists them) and emailed when
 * notifications.email_enabled is on. Sent by employees:send-expiry-reminders.
 */
class EmployeeRecordExpiring extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly EmployeeDocument|EmployeeContract $record) {}

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

        return (new MailMessage)
            ->subject($data['title'])
            ->line($data['message'])
            ->action(__('employees.reminders.open'), $data['url']);
    }

    /**
     * @return array{kind: string, record_id: int, employee_id: int, expires_on: string, title: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $isDocument = $this->record instanceof EmployeeDocument;
        $employee = $this->record->employee()->withoutGlobalScopes()->first();
        $date = ($isDocument ? $this->record->expiry_date : $this->record->end_date)->toDateString();
        $forEmployee = $notifiable instanceof User && $notifiable->employee_id === $employee->getKey() && $notifiable->isEmployee();
        $locale = $notifiable instanceof User ? $notifiable->locale : null;

        return [
            'kind' => $isDocument ? 'document_expiring' : 'contract_expiring',
            'record_id' => $this->record->getKey(),
            'employee_id' => $employee->getKey(),
            'expires_on' => $date,
            'title' => __($isDocument ? 'employees.reminders.document_title' : 'employees.reminders.contract_title', [], $locale),
            'message' => __($isDocument ? 'employees.reminders.document_message' : 'employees.reminders.contract_message', [
                'title' => $isDocument ? $this->record->title : $this->record->contract_type->label(),
                'employee' => $employee->employee_number.' · '.$employee->localized('name', $locale),
                'date' => $date,
            ], $locale),
            'url' => $forEmployee ? route('portal.profile', ['tab' => 'documents']) : route('admin.employees.tab', [$employee, $isDocument ? 'documents' : 'contracts']),
        ];
    }
}
