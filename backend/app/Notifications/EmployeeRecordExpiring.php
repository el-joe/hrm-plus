<?php

namespace App\Notifications;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\User;

/**
 * "A document / contract expires on <date>" (templates "document_expiring", "contract_expiring"),
 * sent by employees:send-expiry-reminders to the employee and to HR.
 */
class EmployeeRecordExpiring extends CentreNotification
{
    public function __construct(public readonly EmployeeDocument|EmployeeContract $record) {}

    public function event(): string
    {
        return $this->isDocument() ? 'document_expiring' : 'contract_expiring';
    }

    protected function actionLabelKey(): string
    {
        return 'employees.reminders.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $employee = $this->employee();

        return [
            'title' => $this->isDocument() ? $this->record->title : $this->record->contract_type->label(),
            'employee' => $employee->employee_number.' · '.$employee->localized('name', $locale),
            'date' => $this->expiresOn(),
        ];
    }

    /**
     * @return array{kind: string, record_id: int, employee_id: int, expires_on: string, url: string}
     */
    public function payload(object $notifiable): array
    {
        $employee = $this->employee();
        $forEmployee = $notifiable instanceof User && $notifiable->employee_id === $employee->getKey() && $notifiable->isEmployee();

        return [
            'kind' => $this->event(),
            'record_id' => $this->record->getKey(),
            'employee_id' => $employee->getKey(),
            'expires_on' => $this->expiresOn(),
            'url' => $forEmployee
                ? route('portal.profile', ['tab' => 'documents'])
                : route('admin.employees.tab', [$employee, $this->isDocument() ? 'documents' : 'contracts']),
        ];
    }

    private function isDocument(): bool
    {
        return $this->record instanceof EmployeeDocument;
    }

    private function expiresOn(): string
    {
        return ($this->isDocument() ? $this->record->expiry_date : $this->record->end_date)->toDateString();
    }

    private function employee(): Employee
    {
        return $this->record->employee()->withoutGlobalScopes()->first();
    }
}
