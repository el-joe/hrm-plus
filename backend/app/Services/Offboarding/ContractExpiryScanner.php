<?php

namespace App\Services\Offboarding;

use App\Actions\Offboarding\OpenOffboardingCase;
use App\Enums\OffboardingSource;
use App\Enums\OffboardingStatus;
use App\Enums\TerminationType;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\OffboardingCase;
use App\Models\User;
use App\Notifications\Lifecycle\OffboardingCaseOpened;
use Illuminate\Support\Facades\Notification;

/**
 * Daily (offboarding:detect-contract-expiries): every contract ending within
 * lifecycle.contract_expiry_case_days that was not renewed or terminated,
 * of an employee still employed and without an open case, gets a
 * "contract end" offboarding case dated on the contract's end date. Admins
 * who may update offboarding cases and see the employee are notified.
 * A contract only ever opens one case, so the run is idempotent; renewing
 * the contract before the date and cancelling the case keeps the employee.
 */
class ContractExpiryScanner
{
    public function __construct(private readonly OpenOffboardingCase $open) {}

    /**
     * @return int cases opened
     */
    public function run(): int
    {
        $contracts = EmployeeContract::query()->withoutGlobalScopes()
            ->whereNull('terminated_on')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', today())
            ->whereDate('end_date', '<=', today()->addDays((int) setting('lifecycle.contract_expiry_case_days')))
            ->whereDoesntHave('renewedBy')
            ->whereHas('employee', fn ($query) => $query->withoutGlobalScopes()->employed())
            ->whereNotExists(fn ($query) => $query->from('offboarding_cases')->whereColumn('offboarding_cases.employee_contract_id', 'employee_contracts.id'))
            ->get();

        $opened = 0;

        foreach ($contracts as $contract) {
            $employee = Employee::query()->withoutGlobalScopes()->findOrFail($contract->employee_id);

            if (OffboardingCase::query()->withoutGlobalScopes()->where('employee_id', $employee->getKey())->where('status', OffboardingStatus::Open)->exists()) {
                continue;
            }

            $case = $this->open->handle($employee, [
                'type' => TerminationType::ContractEnd->value,
                'last_working_date' => $contract->end_date->toDateString(),
                'reason' => __('onboarding.offboarding.contract_expiry_reason', ['date' => $contract->end_date->toDateString()]),
                'eligible_for_rehire' => true,
            ], null, OffboardingSource::ContractExpiry, $contract);

            $recipients = User::query()->admins()->where('status', UserStatus::Active)->with('dataScopes')->get()
                ->filter(fn (User $admin): bool => $admin->can('offboarding.update') && $employee->isVisibleTo($admin))
                ->values();

            Notification::send($recipients, new OffboardingCaseOpened($case));
            $opened++;
        }

        return $opened;
    }
}
