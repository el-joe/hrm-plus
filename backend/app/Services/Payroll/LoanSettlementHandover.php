<?php

namespace App\Services\Payroll;

use App\Enums\HandoverCategory;
use App\Enums\LoanStatus;
use App\Enums\TaskAssignee;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\OffboardingCase;
use App\Services\Offboarding\Contracts\HandoverItemProvider;
use App\Support\Money;

/**
 * Offboarding handover provider (config lifecycle.handover_providers): one
 * finance item per open loan / advance, linked to the loan, so the final
 * settlement recovers it ("Recover in final pay" on the loan page moves the
 * whole balance into one instalment due on the last working day).
 */
class LoanSettlementHandover implements HandoverItemProvider
{
    public function itemsFor(Employee $employee, OffboardingCase $case): array
    {
        $arabic = setting('localisation.default_locale') === 'ar';

        return EmployeeLoan::query()->withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LoanStatus::Active, LoanStatus::Pending])
            ->orderBy('id')->get()
            ->map(fn (EmployeeLoan $loan): array => [
                'category' => HandoverCategory::Finance->value,
                'assignee' => TaskAssignee::Finance->value,
                'title' => __('payroll.loans.handover_title', [
                    'reference' => $loan->reference,
                    'amount' => Money::format((string) $loan->outstanding_amount),
                ], $arabic ? 'ar' : 'en'),
                'description' => __('payroll.loans.handover_description', [], $arabic ? 'ar' : 'en'),
                'itemable_type' => $loan->getMorphClass(),
                'itemable_id' => $loan->id,
            ])->values()->all();
    }
}
