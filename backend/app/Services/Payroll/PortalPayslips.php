<?php

namespace App\Services\Payroll;

use App\Enums\LoanStatus;
use App\Enums\PayCalculationType;
use App\Enums\PayslipStatus;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Payroll\Data\ComponentRule;
use App\Services\Payroll\Data\PayEntryInput;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * "My payroll" for the portal and the mobile API (spec section 5): the
 * signed-in employee's own issued payslips only (admin data scopes ignored,
 * void payslips hidden), a year summary, the loan balance and — when setting
 * payroll.portal_show_structure is on — the current salary structure. Every
 * payslip view and download is logged (sensitive_access.payslip).
 */
class PortalPayslips
{
    public function __construct(private readonly PayrollInputs $inputs, private readonly SensitiveDataAccessLog $log) {}

    /**
     * @return Builder<Payslip>
     */
    public function payslips(Employee $employee): Builder
    {
        return $this->base($employee)->with(['period', 'run'])->select('payslips.*')
            ->orderByDesc('payroll_periods.end_date')->orderByDesc('payslips.id');
    }

    /**
     * Own issued payslips joined to their period (no select / order).
     *
     * @return Builder<Payslip>
     */
    private function base(Employee $employee): Builder
    {
        return Payslip::query()->withoutGlobalScopes()
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payslips.payroll_period_id')
            ->where('payslips.employee_id', $employee->id)->where('payslips.status', PayslipStatus::Issued);
    }

    public function paginate(Employee $employee, ?int $year = null, int $perPage = 12): LengthAwarePaginator
    {
        return $this->payslips($employee)
            ->when($year, fn (Builder $query, int $year) => $query->whereYear('payroll_periods.end_date', $year))
            ->paginate($perPage)->withQueryString();
    }

    public function find(Employee $employee, int $id): ?Payslip
    {
        return $this->payslips($employee)->where('payslips.id', $id)->first();
    }

    /**
     * Years with payslips, newest first.
     *
     * @return list<int>
     */
    public function years(Employee $employee): array
    {
        return $this->base($employee)->selectRaw('distinct year(payroll_periods.end_date) as year')->orderByDesc('year')
            ->pluck('year')->map(fn ($year): int => (int) $year)->all();
    }

    /**
     * @return array{year: int, count: int, gross: string, deductions: string, net: string, paid: string}
     */
    public function yearSummary(Employee $employee, int $year): array
    {
        $row = $this->base($employee)->whereYear('payroll_periods.end_date', $year)
            ->selectRaw('count(*) as count, coalesce(sum(payslips.gross_pay),0) as gross, coalesce(sum(payslips.total_deductions),0) as deductions, coalesce(sum(payslips.net_pay),0) as net, coalesce(sum(payslips.amount_paid),0) as paid')
            ->first();

        return [
            'year' => $year, 'count' => (int) $row->count, 'gross' => bcadd((string) $row->gross, '0', 2),
            'deductions' => bcadd((string) $row->deductions, '0', 2), 'net' => bcadd((string) $row->net, '0', 2), 'paid' => bcadd((string) $row->paid, '0', 2),
        ];
    }

    public function loanBalance(Employee $employee): string
    {
        return bcadd((string) EmployeeLoan::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->where('status', LoanStatus::Active)->sum('outstanding_amount'), '0', 2);
    }

    /**
     * The salary structure in force this month (lines as rules, not a calculation), or null when hidden / missing.
     *
     * @return array{basic: ?string, pay_basis: ?string, structure: ?string, lines: list<array{name: string, kind: string, rule: string}>, allowances: list<array{name: string, kind: string, rule: string}>}|null
     */
    public function structure(Employee $employee): ?array
    {
        if (! setting('payroll.portal_show_structure')) {
            return null;
        }

        $inputs = $this->inputs->for($employee, now()->startOfMonth(), now()->endOfMonth()->startOfDay());
        $describe = fn (ComponentRule $rule): string => match ($rule->calculationType) {
            PayCalculationType::Basic => Money::format($inputs->basicSalary),
            PayCalculationType::Fixed, PayCalculationType::Variable => Money::format($rule->amount),
            PayCalculationType::Percentage => __('payroll.common.percent_of', ['rate' => PayrollMath::trim((string) $rule->rate), 'base' => $rule->baseCode ?? __('payroll.common.basic_salary')]),
            default => $rule->calculationType->label(),
        };

        return [
            'basic' => $inputs->basicSalary,
            'pay_basis' => $inputs->payBasis?->label(),
            'structure' => $inputs->structure?->name,
            'lines' => array_map(fn (ComponentRule $rule): array => ['name' => $rule->name(), 'kind' => $rule->kind->value, 'rule' => $describe($rule)],
                array_values(array_filter($inputs->structure?->lines ?? [], fn (ComponentRule $rule): bool => $rule->showOnPayslip && $rule->kind->value !== 'employer_contribution' && $rule->calculationType !== PayCalculationType::Basic))),
            'allowances' => array_map(fn (PayEntryInput $entry): array => ['name' => $entry->rule->name(), 'kind' => $entry->rule->kind->value, 'rule' => $describe($entry->rule)], $inputs->entries),
        ];
    }

    /**
     * Log a view / download by the employee (portal or API).
     */
    public function logAccess(Payslip $payslip, User $user, string $action, string $channel): void
    {
        $this->log->record(SensitiveDataAccessLog::PAYSLIP, $payslip, ['net_pay', 'lines'], ['action' => $action, 'channel' => $channel, 'employee_id' => $payslip->employee_id, 'own' => true], $user);
    }
}
