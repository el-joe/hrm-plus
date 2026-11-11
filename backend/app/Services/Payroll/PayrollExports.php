<?php

namespace App\Services\Payroll;

use App\Models\EmployeeBankAccount;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
use App\Models\PayrollRun;
use App\Services\Employees\Spreadsheet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Payroll files written with openspout (App\Services\Employees\Spreadsheet):
 *
 * - register (XLSX / CSV): one row per employee — placement, pay basis, basic,
 *   one column per earning / deduction / employer component of the run,
 *   gross, deductions, net;
 * - bank transfer file (generic CSV / XLSX): employee, bank, account holder,
 *   IBAN, account number, SWIFT, amount (net pay), currency, payment
 *   reference. Employees without a primary bank account are listed with an
 *   empty account and the "missing bank account" note so the file can be
 *   checked before upload. Only positive net pay is transferred.
 *
 * Rows follow the viewer's data scope (PayrollItem is scoped).
 */
class PayrollExports
{
    public function __construct(private readonly Spreadsheet $spreadsheet) {}

    public function register(PayrollRun $run, string $path, string $format): void
    {
        $components = PayrollItemLine::query()->where('payroll_run_id', $run->id)
            ->selectRaw('code, kind, max(name_ar) as name_ar, max(name_en) as name_en, min(display_order) as sort')
            ->groupBy('code', 'kind')->orderByRaw("field(kind, 'earning', 'deduction', 'employer_contribution')")->orderBy('sort')->get();
        $label = fn ($row): string => (app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en).' ('.$row->code.')';

        $headers = [
            __('payroll_runs.exports.employee_number'), __('payroll_runs.exports.employee'), __('payroll_runs.exports.branch'), __('payroll_runs.exports.department'),
            __('payroll_runs.exports.pay_basis'), __('payroll_runs.exports.basic_salary'),
            ...$components->map($label)->all(),
            __('payroll_runs.exports.gross'), __('payroll_runs.exports.deductions'), __('payroll_runs.exports.net'), __('payroll_runs.exports.employer'),
        ];

        $rows = $this->items($run)->lazyById(200)->map(function (PayrollItem $item) use ($components): array {
            $amounts = $item->lines->groupBy(fn (PayrollItemLine $line): string => $line->kind->value.'|'.$line->code)
                ->map(fn (Collection $lines): string => $lines->reduce(fn (string $sum, PayrollItemLine $line): string => bcadd($sum, (string) $line->amount, 2), '0.00'));

            return [
                $item->employee->employee_number, $item->employee->name, $item->branch?->name, $item->department?->name,
                $item->pay_basis?->label(), $item->basic_salary === null ? '' : (string) $item->basic_salary,
                ...$components->map(fn ($row): string => $amounts->get($row->getRawOriginal('kind').'|'.$row->code, ''))->all(),
                (string) $item->gross_pay, (string) $item->total_deductions, (string) $item->net_pay, (string) $item->employer_contributions,
            ];
        });

        $this->spreadsheet->write($path, $format, $headers, $rows);
    }

    public function bankFile(PayrollRun $run, string $path, string $format): void
    {
        $headers = [
            __('payroll_runs.exports.employee_number'), __('payroll_runs.exports.employee'), __('payroll_runs.exports.bank_name'), __('payroll_runs.exports.account_holder'),
            __('payroll_runs.exports.iban'), __('payroll_runs.exports.account_number'), __('payroll_runs.exports.swift'), __('payroll_runs.exports.amount'),
            __('payroll_runs.exports.currency'), __('payroll_runs.exports.reference'), __('payroll_runs.exports.note'),
        ];

        $rows = $this->items($run)->where('net_pay', '>', 0)->lazyById(200)->map(function (PayrollItem $item) use ($run): array {
            $account = EmployeeBankAccount::query()->where('employee_id', $item->employee_id)->orderByDesc('is_primary')->orderBy('id')->first();

            return [
                $item->employee->employee_number, $item->employee->name, $account?->bank_name ?? '', $account?->account_holder ?? '',
                $account?->iban ?? '', $account?->account_number ?? '', $account?->swift_code ?? '', (string) $item->net_pay, $item->currency,
                $item->payslip?->number ?? $run->reference, $account === null ? __('payroll_runs.exports.missing_account') : '',
            ];
        });

        $this->spreadsheet->write($path, $format, $headers, $rows);
    }

    /**
     * @return Builder<PayrollItem>
     */
    private function items(PayrollRun $run): Builder
    {
        return PayrollItem::query()->where('payroll_run_id', $run->id)
            ->with(['employee' => fn ($query) => $query->withoutGlobalScopes(), 'branch', 'department', 'lines', 'payslip'])
            ->orderBy('id');
    }
}
