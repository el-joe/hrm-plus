<?php

namespace App\Services\Employees;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Notifications\EmployeeRecordExpiring;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Reminders for documents and contracts that expire within the lead time
 * set in the notifications settings. Each record is reminded once per expiry
 * date (expiry_reminded_for), so the daily run is idempotent and a renewed
 * expiry date re-arms the reminder.
 *
 * Recipients: active admins who may edit the record (employee_documents.update
 * / employees.update) and whose data scope includes the employee; for
 * documents shared with the employee, the employee's own account too.
 */
class ExpiryReminders
{
    /**
     * @return array{documents: int, contracts: int}
     */
    public function send(): array
    {
        $documents = EmployeeDocument::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays((int) setting('notifications.document_expiry_reminder_days')))
            ->where(fn ($query) => $query->whereNull('expiry_reminded_for')->orWhereColumn('expiry_reminded_for', '!=', 'expiry_date'))
            ->whereHas('employee', fn ($query) => $query->withoutGlobalScopes()->employed())
            ->get();

        $contracts = EmployeeContract::query()->withoutGlobalScopes()
            ->whereNull('terminated_on')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', today()->addDays((int) setting('notifications.contract_expiry_reminder_days')))
            ->where(fn ($query) => $query->whereNull('expiry_reminded_for')->orWhereColumn('expiry_reminded_for', '!=', 'end_date'))
            ->whereDoesntHave('renewedBy')
            ->whereHas('employee', fn ($query) => $query->withoutGlobalScopes()->employed())
            ->get();

        foreach ($documents as $document) {
            $employee = Employee::query()->withoutGlobalScopes()->findOrFail($document->employee_id);
            $recipients = $this->admins('employee_documents.update', $employee);

            if ($document->visible_to_employee) {
                $recipients = $recipients->merge($this->employeeAccounts($employee));
            }

            Notification::send($recipients, new EmployeeRecordExpiring($document));
            $document->forceFill(['expiry_reminded_for' => $document->expiry_date])->saveQuietly();
        }

        foreach ($contracts as $contract) {
            $employee = Employee::query()->withoutGlobalScopes()->findOrFail($contract->employee_id);

            Notification::send($this->admins('employees.update', $employee), new EmployeeRecordExpiring($contract));
            $contract->forceFill(['expiry_reminded_for' => $contract->end_date])->saveQuietly();
        }

        return ['documents' => $documents->count(), 'contracts' => $contracts->count()];
    }

    /**
     * @return Collection<int, User>
     */
    private function admins(string $permission, Employee $employee): Collection
    {
        return User::query()->admins()->where('status', UserStatus::Active)->with('dataScopes')->get()
            ->filter(fn (User $admin): bool => $admin->can($permission) && $employee->isVisibleTo($admin))
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    private function employeeAccounts(Employee $employee): Collection
    {
        return User::query()->where('employee_id', $employee->getKey())
            ->where('account_type', AccountType::Employee)->where('status', UserStatus::Active)->get();
    }
}
