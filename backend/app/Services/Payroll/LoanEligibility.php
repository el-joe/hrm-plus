<?php

namespace App\Services\Payroll;

use App\Enums\EmployeeStatus;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\PayBasis;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Services\Settings\SettingsService;
use App\Support\Money;
use Carbon\CarbonImmutable;

/**
 * Loan and advance limits from the "loans" settings group, checked on every
 * request (portal, API, admin on behalf) and again when an approver changes
 * the amount or the number of instalments:
 *
 * - the employee is active and has served loans.min_service_months;
 * - open loans (pending + active) stay below loans.max_active_loans;
 * - a loan is at most loans.max_loan_salary_multiple × the monthly basic salary
 *   over at most loans.max_loan_instalments instalments; an advance at most
 *   loans.max_advance_percent % of the basic salary over at most loans.max_advance_instalments;
 * - one loan instalment is at most loans.max_instalment_percent % of the basic salary
 *   (advances are bounded by their own percentage and instalment count).
 *
 * Non-monthly pay bases are converted to a monthly equivalent with
 * payroll.working_days_per_month / working_hours_per_day.
 */
class LoanEligibility
{
    public function __construct(private readonly SettingsService $settings, private readonly LoanSchedule $schedule) {}

    /**
     * @return array{max_amount: string, max_instalments: int, max_instalment: ?string, monthly_salary: ?string, min_service_months: int}
     */
    public function limits(Employee $employee, LoanType $type): array
    {
        $salary = $this->monthlySalary($employee);
        $max = $salary === null ? '0.00' : match ($type) {
            LoanType::Loan => bcmul($salary, (string) $this->settings->get('loans.max_loan_salary_multiple'), 2),
            LoanType::Advance => PayrollMath::round(PayrollMath::percent($salary, (string) $this->settings->get('loans.max_advance_percent')), 2),
        };

        return [
            'max_amount' => $max,
            'max_instalments' => (int) $this->settings->get($type === LoanType::Loan ? 'loans.max_loan_instalments' : 'loans.max_advance_instalments'),
            'max_instalment' => $type === LoanType::Advance ? null : ($salary === null ? '0.00' : PayrollMath::round(PayrollMath::percent($salary, (string) $this->settings->get('loans.max_instalment_percent')), 2)),
            'monthly_salary' => $salary,
            'min_service_months' => (int) $this->settings->get('loans.min_service_months'),
        ];
    }

    /**
     * Reasons the request is refused, keyed by field (empty = eligible).
     *
     * @return array<string, string>
     */
    public function reasons(Employee $employee, LoanType $type, string $amount, int $instalments, ?int $ignoreLoanId = null): array
    {
        $limits = $this->limits($employee, $type);
        $reasons = [];

        if ($employee->status !== EmployeeStatus::Active) {
            return ['type' => __('payroll.loans.validation.not_active')];
        }

        if ($limits['monthly_salary'] === null || bccomp($limits['monthly_salary'], '0', 2) <= 0) {
            return ['type' => __('payroll.loans.validation.no_salary')];
        }

        if ($employee->hire_date !== null && CarbonImmutable::parse($employee->hire_date)->addMonthsNoOverflow($limits['min_service_months'])->isFuture()) {
            $reasons['type'] = __('payroll.loans.validation.min_service', ['months' => $limits['min_service_months']]);
        }

        $open = EmployeeLoan::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->whereIn('status', [LoanStatus::Pending, LoanStatus::Active])
            ->when($ignoreLoanId, fn ($query, int $id) => $query->whereKeyNot($id))->count();

        if ($open >= (int) $this->settings->get('loans.max_active_loans')) {
            $reasons['type'] ??= __('payroll.loans.validation.max_active', ['count' => $this->settings->get('loans.max_active_loans')]);
        }

        if (bccomp($amount, $limits['max_amount'], 2) > 0) {
            $reasons['amount'] = __('payroll.loans.validation.max_amount', ['max' => Money::format($limits['max_amount'])]);
        }

        if ($instalments > $limits['max_instalments']) {
            $reasons['instalments'] = __('payroll.loans.validation.max_instalments', ['max' => $limits['max_instalments']]);
        } elseif ($type === LoanType::Loan && bccomp($this->schedule->instalmentAmount($amount, $instalments), $limits['max_instalment'], 2) > 0) {
            $reasons['instalments'] = __('payroll.loans.validation.max_instalment', ['max' => Money::format($limits['max_instalment'])]);
        }

        return $reasons;
    }

    /**
     * Monthly equivalent of the basic salary in force today (null without a salary).
     */
    public function monthlySalary(Employee $employee): ?string
    {
        if ($employee->basic_salary === null) {
            return null;
        }

        $salary = (string) $employee->basic_salary;
        $days = (string) $this->settings->get('payroll.working_days_per_month');
        $hours = (string) $this->settings->get('payroll.working_hours_per_day');

        return PayrollMath::round(match ($employee->pay_basis) {
            PayBasis::Daily => PayrollMath::mul($salary, $days),
            PayBasis::Hourly => PayrollMath::mul($salary, $days, $hours),
            default => $salary,
        }, 2);
    }
}
