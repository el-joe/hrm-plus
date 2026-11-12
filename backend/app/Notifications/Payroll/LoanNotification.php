<?php

namespace App\Notifications\Payroll;

use App\Models\EmployeeLoan;
use App\Models\User;
use App\Notifications\CentreNotification;
use App\Support\Money;

/**
 * Loan / advance request and schedule-change events (templates "loan_*"): to approvers
 * (awaiting_approval, change_awaiting_approval) and to the employee / filing admin (the others).
 */
class LoanNotification extends CentreNotification
{
    public const KINDS = ['submitted', 'awaiting_approval', 'approved', 'rejected', 'change_awaiting_approval', 'change_approved', 'change_rejected'];

    public function __construct(public readonly EmployeeLoan $loan, public readonly string $kind, public readonly ?string $reason = null) {}

    public function event(): string
    {
        return 'loan_'.$this->kind;
    }

    protected function actionLabelKey(): string
    {
        return 'payroll.loans.notifications.open';
    }

    /**
     * @return array<string, scalar|null>
     */
    public function placeholders(object $notifiable, ?string $locale): array
    {
        $loan = $this->loan->loadMissing(['employee' => fn ($query) => $query->withoutGlobalScopes()]);

        return [
            'type' => $loan->type->label(),
            'employee' => $loan->employee->localized('name', $locale),
            'reference' => $loan->reference,
            'amount' => Money::format((string) ($loan->approved_amount ?? $loan->requested_amount)),
            'reason' => $this->reason,
        ];
    }

    /**
     * @return array{kind: string, employee_loan_id: int, reason: ?string, url: string}
     */
    public function payload(object $notifiable): array
    {
        $isAdmin = $notifiable instanceof User && $notifiable->isAdmin();

        return [
            'kind' => 'loan_'.$this->kind,
            'employee_loan_id' => $this->loan->id,
            'reason' => $this->reason,
            'url' => $isAdmin ? route('admin.loans.show', $this->loan) : route('portal.loans.show', $this->loan->id),
        ];
    }
}
