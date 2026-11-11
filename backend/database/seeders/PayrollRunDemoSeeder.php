<?php

namespace Database\Seeders;

use App\Actions\Offboarding\ManageOffboardingCase;
use App\Actions\Offboarding\OpenOffboardingCase;
use App\Actions\Payroll\CreateFinalSettlement;
use App\Actions\Payroll\ManagePayrollPeriod;
use App\Actions\Payroll\ManagePayrollRun;
use App\Actions\Payroll\ManagePayslipPayments;
use App\Enums\EmployeeStatus;
use App\Enums\LoanStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\PayrollRunType;
use App\Enums\TaskStatus;
use App\Enums\TerminationType;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\OffboardingCase;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Payroll\PayrollCorrections;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Development demo of payroll runs (Phase 7b), every step through the real
 * actions (population, calculation, approval engine, finalisation, payments):
 *
 * - September 2026: a Cairo branch settlement, then a consolidated run of
 *   everybody else (the Cairo staff are left out — already paid), both
 *   approved by HR then Finance, finalised, and paid by bank transfer;
 * - October 2026: one consolidated run, finalised; most payslips paid, one
 *   partially paid, a few still unpaid;
 * - a manual correction of October (missed transport allowance) recorded
 *   in November, carried by the November run;
 * - a final settlement: an employee with an active loan resigns (offboarding
 *   case, last working day 2026-11-05); the settlement prorates the salary,
 *   encashes leave, recovers the loan (partially — net pay is too low for all
 *   of it), is approved and finalised, then the case is completed (the
 *   employee is terminated);
 * - November 2026: the current consolidated run, calculated and in review
 *   (the leaver is out: paid by the final settlement).
 */
class PayrollRunDemoSeeder extends Seeder
{
    private User $accountant;

    private User $finance;

    private User $hr;

    public function run(): void
    {
        if (PayrollRun::query()->exists()) {
            return;
        }

        config(['queue.default' => 'sync']);
        $this->accountant = User::query()->where('email', 'payroll.accountant@hrmplus.test')->firstOrFail();
        $this->finance = User::query()->where('email', 'finance.officer@hrmplus.test')->firstOrFail();
        $this->hr = User::query()->where('email', 'hr.manager@hrmplus.test')->firstOrFail();
        $periods = app(ManagePayrollPeriod::class);

        $september = $this->at('2026-09-01', fn () => $periods->monthly('2026-09', $this->accountant));
        $october = $this->at('2026-10-01', fn () => $periods->monthly('2026-10', $this->accountant));
        $november = $this->at('2026-11-01', fn () => $periods->monthly('2026-11', $this->accountant, '2026-11-27', '2026-11-25'));

        $cairo = Branch::query()->where('code', 'CAI')->value('id');

        $this->at('2026-09-27', function () use ($september, $cairo): void {
            if ($cairo !== null) {
                $this->complete($this->runs()->create($september, PayrollRunType::Regular, ['branch_ids' => [$cairo]], $this->accountant, 'Cairo branch settlement · September 2026'));
            }

            $this->complete($this->runs()->create($september, PayrollRunType::Regular, ['all' => true], $this->accountant, 'Consolidated settlement · September 2026', 'Everybody not paid by the Cairo settlement'));
        });
        $this->at('2026-09-30', fn () => $this->pay($september, 'BANK-2026-09-30'));

        $this->at('2026-10-29', fn () => $this->complete($this->runs()->create($october, PayrollRunType::Regular, ['all' => true], $this->accountant, 'October 2026 · consolidated')));
        $this->at('2026-10-31', fn () => $this->pay($october, 'BANK-2026-10-31', unpaid: 3, partial: 1));

        $this->at('2026-11-04', fn () => $this->correction($october));
        $this->at('2026-11-06', fn () => $this->finalSettlement());

        $this->at('2026-11-12', function () use ($november): void {
            $run = $this->runs()->create($november, PayrollRunType::Regular, ['all' => true], $this->accountant, 'November 2026 · consolidated');
            $this->runs()->calculate($run, $this->accountant);
        });

        $this->command?->info('Payroll runs demo: '.PayrollRun::query()->count().' runs, '.Payslip::query()->count().' payslips.');
    }

    /**
     * Calculate → submit → HR review → Finance approval → finalise.
     */
    private function complete(PayrollRun $run): PayrollRun
    {
        $runs = $this->runs();
        $run = $runs->calculate($run, $this->accountant);

        foreach ($run->items()->withoutGlobalScopes()->where('has_blocking_warning', true)->get() as $item) {
            $run = $runs->removeEmployee($run, $item, 'Demo: blocking issue, paid separately', $this->accountant);
            $run = $runs->calculate($run, $this->accountant);
        }

        $runs->submit($run, $this->accountant);
        $runs->decide($run->refresh(), $this->hr, true, 'Headcount and variances checked');
        $run = $runs->decide($run->refresh(), $this->finance, true, 'Budget confirmed');

        return $run->status === PayrollRunStatus::Approved ? $runs->finalise($run, $this->finance) : $run;
    }

    /**
     * Record bank payments of the period's payslips, leaving some unpaid / partially paid.
     */
    private function pay(PayrollPeriod $period, string $reference, int $unpaid = 0, int $partial = 0): void
    {
        $payslips = Payslip::query()->where('payroll_period_id', $period->id)->where('payment_status', 'unpaid')->orderBy('id')->get();
        $payments = app(ManagePayslipPayments::class);
        $later = $payslips->take(-1 * ($unpaid + $partial));
        $now = $payslips->slice(0, $payslips->count() - $later->count());

        $payments->markPaid($now, ['paid_on' => now()->toDateString(), 'method' => 'bank_transfer', 'reference' => $reference], $this->finance);

        foreach ($later->take($partial) as $payslip) {
            $payments->record($payslip, [
                'amount' => bcdiv((string) $payslip->net_pay, '2', 2), 'paid_on' => now()->toDateString(), 'method' => 'cash',
                'reference' => null, 'note' => 'Half paid in cash; the rest with the next transfer',
            ], $this->finance);
        }
    }

    private function correction(PayrollPeriod $october): void
    {
        $item = PayrollItem::query()->where('payroll_period_id', $october->id)->where('pay_slot', PayrollItem::PAY_SLOT)
            ->whereHas('employee', fn ($query) => $query->where('status', EmployeeStatus::Active))->orderBy('id')->skip(4)->first();

        if ($item === null) {
            return;
        }

        app(PayrollCorrections::class)->manual($item->employee, [
            'employee_id' => $item->employee_id, 'kind' => 'earning', 'amount' => '350.00', 'original_period_id' => $october->id,
            'reason' => 'Transport allowance for the October site visits was approved after the run was finalised',
        ], $this->accountant);
    }

    /**
     * An employee with an active loan resigns; final settlement, then the case is completed.
     */
    private function finalSettlement(): void
    {
        $accounts = User::query()->whereNotNull('employee_id')->pluck('employee_id');
        $loan = EmployeeLoan::query()->where('status', LoanStatus::Active)->whereNotIn('employee_id', $accounts)
            ->whereHas('employee', fn ($query) => $query->where('status', EmployeeStatus::Active))
            ->whereDoesntHave('employee.offboardingCases')->orderBy('id')->first();
        $employee = $loan?->employee ?? Employee::query()->active()->whereNotIn('id', $accounts)->whereDoesntHave('offboardingCases')->orderByDesc('id')->first();

        if ($employee === null) {
            return;
        }

        $case = app(OpenOffboardingCase::class)->handle($employee, [
            'type' => TerminationType::Resignation->value, 'notice_date' => '2026-10-06', 'last_working_date' => '2026-11-05',
            'reason' => 'Moving abroad.', 'eligible_for_rehire' => true,
        ], $this->hr);

        $manage = app(ManageOffboardingCase::class);
        $case->items()->get()
            ->reject(fn ($item): bool => $item->itemable_type === (new OffboardingCase)->getMorphClass())
            ->each(fn ($item) => $manage->updateItem($item, TaskStatus::Done, $this->hr));

        $settlement = app(CreateFinalSettlement::class);
        $encash = collect($settlement->encashable($employee, '2026-11-05'))->mapWithKeys(fn (array $row): array => [
            $row['type']->id => $row['max'] !== null && bccomp($row['max'], $row['available'], 2) < 0 ? $row['max'] : $row['available'],
        ])->all();

        $this->complete($settlement->handle($case, $this->accountant, $encash, 'Resignation, last working day 5 November'));
        $manage->complete($case->refresh(), $this->hr);
    }

    private function runs(): ManagePayrollRun
    {
        return app(ManagePayrollRun::class);
    }

    /**
     * Run a step as if now were the given date (morning, Cairo business hours).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function at(string $date, callable $callback): mixed
    {
        Carbon::setTestNow(CarbonImmutable::parse($date.' 09:30:00'));

        try {
            return $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
