<?php

namespace App\Actions\Payroll;

use App\Actions\Leave\AdjustLeaveBalance;
use App\Enums\LeaveTransactionType;
use App\Enums\PayrollRunType;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\OffboardingCase;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeavePolicyResolver;
use App\Services\Leave\LeaveYear;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The off-cycle final settlement of a leaving employee, wired to the
 * offboarding case ("Final financial settlement" handover item):
 *
 * - the monthly period containing the last working day (created if missing);
 *   the settlement takes the employee's pay slot of that period, so no regular
 *   run can pay them again (and it is refused when a run already paid them);
 * - leave encashment: the days chosen per encashable leave type are booked
 *   through the leave module's own rules (AdjustLeaveBalance: policy encashable,
 *   yearly maximum, available balance) and reach payroll through LeavePayrollFeed;
 * - the calculation (PayrollCalculator, type final_settlement) prorates the
 *   salary to the termination date and recovers every scheduled loan / advance
 *   instalment; pending adjustments and final deductions (entries) are included.
 *
 * Approval and finalisation follow the normal run workflow; finalising marks
 * the case's settlement item done.
 */
class CreateFinalSettlement
{
    public function __construct(
        private readonly ManagePayrollRun $runs,
        private readonly ManagePayrollPeriod $periods,
        private readonly AdjustLeaveBalance $adjustLeave,
        private readonly LeaveBalanceCalculator $balances,
        private readonly LeavePolicyResolver $policies,
        private readonly LeaveYear $years,
    ) {}

    /**
     * Encashable leave balances of the employee on the date: [{type, available, max}].
     *
     * @return list<array{type: LeaveType, available: string, max: ?string, year: int}>
     */
    public function encashable(Employee $employee, string $date): array
    {
        $year = $this->years->of(CarbonImmutable::parse($date));
        $rows = [];

        foreach (LeaveType::query()->where('tracks_balance', true)->orderBy('id')->get() as $type) {
            $policy = $this->policies->for($employee, $type, CarbonImmutable::parse($date));

            if ($policy === null || ! $policy->encashable) {
                continue;
            }

            $available = $this->balances->for($employee, $type, $year)->available;

            if (bccomp($available, '0', 2) <= 0) {
                continue;
            }

            $rows[] = ['type' => $type, 'available' => $available, 'max' => $policy->encashment_max_days === null ? null : (string) $policy->encashment_max_days, 'year' => $year];
        }

        return $rows;
    }

    /**
     * @param  array<int|string, string|null>  $encash  leave type id => days to encash
     */
    public function handle(OffboardingCase $case, User $user, array $encash = [], ?string $notes = null): PayrollRun
    {
        $employee = Employee::query()->withoutGlobalScopes()->findOrFail($case->employee_id);
        $lastDay = ($employee->termination_date ?? $case->last_working_date)?->toDateString();

        if ($lastDay === null) {
            throw ValidationException::withMessages(['case' => __('payroll_runs.final_settlement.no_last_day')]);
        }

        if (PayrollRun::query()->withoutGlobalScopes()->where('offboarding_case_id', $case->id)->where('type', PayrollRunType::FinalSettlement)
            ->whereNotIn('status', ['cancelled', 'reversed'])->exists()) {
            throw ValidationException::withMessages(['case' => __('payroll_runs.final_settlement.exists')]);
        }

        return DB::transaction(function () use ($case, $user, $encash, $notes, $employee, $lastDay): PayrollRun {
            $period = $this->periods->forDate($lastDay, $user);
            $year = $this->years->of(CarbonImmutable::parse($lastDay));

            foreach ($encash as $typeId => $days) {
                if (! filled($days) || bccomp((string) $days, '0', 2) <= 0) {
                    continue;
                }

                $this->adjustLeave->handle($employee, [
                    'leave_type_id' => $typeId, 'leave_year' => $year, 'type' => LeaveTransactionType::Encashment->value,
                    'amount' => (string) $days, 'effective_date' => $lastDay,
                    'reason' => __('payroll_runs.final_settlement.encashment_reason', ['case' => $case->id], (string) setting('localisation.default_locale')),
                ], $user);
            }

            return $this->runs->create($period, PayrollRunType::FinalSettlement, ['employee_ids' => [$employee->id]], $user,
                __('payroll_runs.final_settlement.run_name', ['employee' => $employee->label()]), $notes, $case);
        });
    }
}
