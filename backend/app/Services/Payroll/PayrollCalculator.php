<?php

namespace App\Services\Payroll;

use App\Enums\LoanType;
use App\Enums\PayBasis;
use App\Enums\PayCalculationType;
use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayEntryRecurrence;
use App\Enums\PayrollLineSource;
use App\Enums\PayrollRunType;
use App\Enums\StatutoryType;
use App\Exceptions\PayrollConfigurationException;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Services\Leave\LeavePayrollFeed;
use App\Services\Payroll\Data\CalculationContext;
use App\Services\Payroll\Data\ComponentRule;
use App\Services\Payroll\Data\EmployeePayrollInputs;
use App\Services\Payroll\Data\LoanInstalmentInput;
use App\Services\Payroll\Data\PayEntryInput;
use App\Services\Payroll\Data\PayrollLine;
use App\Services\Payroll\Data\PayrollResult;
use App\Services\Payroll\Data\SalarySegment;
use App\Services\Payroll\Data\StatutoryRule;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The payroll engine: the pay of one employee for one run, line by line,
 * with an explanation for every amount. Pure with respect to the database
 * (it reads, never writes). Contract of its inputs: docs/PAYROLL_ENGINE.md.
 *
 * Regular runs and final settlements ("pays salary"):
 *
 * 1. Employment window = the period clipped to the hire / termination dates;
 *    salary segments clipped to it (joiners, leavers, mid-period changes).
 * 2. Per salary segment, a CalculationContext with the segment's rates and
 *    its own attendance (AttendanceQuantities) and unpaid leave
 *    (LeavePayrollFeed) quantities; the segment-sensitive structure rules
 *    (basic, per-minute / per-day / overtime) are evaluated per segment and
 *    summed. Pay basis: monthly = calendar-day proration + attendance
 *    deductions; daily = day rate × paid days (attended + holidays + paid
 *    leave); hourly = hour rate × worked hours; fixed = the full amount of the
 *    salary in force at the period end, no proration, no attendance
 *    deductions (overtime still paid).
 * 3. Fixed / percentage structure lines once, on the combined context.
 * 4. Allowance / deduction entries (they ADD to structure lines), leave
 *    encashment (days × day rate), pending post-finalisation adjustments.
 * 5. Statutory lines of the structure with the version in force: social
 *    insurance on insurable pay, income tax on taxable pay; the base reductions
 *    are configured on the statutory version (categories / codes of deduction
 *    lines, and for tax the employee's insurance share).
 * 6. Loan instalments due (a final settlement recovers the whole outstanding
 *    balance); when net pay cannot cover them they are recovered partially.
 *
 * Correction runs only carry the employee's pending adjustments.
 */
class PayrollCalculator
{
    public function __construct(
        private readonly PayrollInputs $payrollInputs,
        private readonly ComponentCalculator $components,
        private readonly SalaryRates $rates,
        private readonly Proration $proration,
        private readonly StatutoryCalculator $statutory,
        private readonly AttendanceQuantities $attendance,
        private readonly LeavePayrollFeed $leave,
        private readonly LoanInstalments $loans,
        private readonly PayrollPlacement $placement,
    ) {}

    /**
     * @param  Collection<int, PayrollAdjustment>|null  $adjustments  pending adjustments to carry
     * @param  string|null  $leavingDate  last working day of a final settlement prepared before the
     *                                    offboarding case is completed (the employee is not terminated yet)
     */
    public function calculate(Employee $employee, PayrollPeriod $period, PayrollRunType $type, ?Collection $adjustments = null, ?string $leavingDate = null): PayrollResult
    {
        $start = $period->start_date->toImmutable()->startOfDay();
        $end = $period->end_date->toImmutable()->startOfDay();
        $inputs = $this->payrollInputs->for($employee, $start, $end);
        $adjustments ??= collect();

        $state = new class
        {
            /** @var list<PayrollLine> */
            public array $lines = [];

            /** @var list<array{code: string, params: array<string, string|int>, blocking: bool}> */
            public array $warnings = [];

            /** @var array<string, mixed> */
            public array $quantities = [];

            /** @var array<string, mixed> */
            public array $snapshot = [];

            /** @var list<array{instalment_id: int, amount: string}> */
            public array $loanRecoveries = [];

            public string $factor = '0';

            public ?string $basic = null;

            public ?PayBasis $basis = null;
        };

        $state->quantities['placement'] = $this->placement->segments($employee, $start, $end);

        if ($type->paysSalary()) {
            try {
                $this->salary($employee, $period, $inputs, $state, $leavingDate);
            } catch (PayrollConfigurationException $exception) {
                $state->lines = [];
                $this->warn($state, 'configuration_error', ['message' => $exception->getMessage()], true);
            }
        }

        foreach ($adjustments as $adjustment) {
            $state->lines[] = $this->adjustmentLine($adjustment);
        }

        if ($type->paysSalary()) {
            $this->statutoryLines($inputs, $period, $state);
            $this->loanLines($employee, $inputs, $type, $start, $state);
        }

        [$gross, $deductions, $employer, $net] = $this->totals($state->lines);

        if (bccomp($net, '0', 2) < 0 && $state->loanRecoveries !== []) {
            $net = $this->capLoans($state, $net);
            [$gross, $deductions, $employer, $net] = $this->totals($state->lines);
        }

        if (bccomp($net, '0', 2) < 0) {
            $this->warn($state, 'negative_net', ['amount' => Money::format($net)], true);
        }

        $taxable = $this->sumWhere($state->lines, fn (PayrollLine $line): bool => $line->kind === PayComponentKind::Earning && $line->taxable);
        $insurable = $this->sumWhere($state->lines, fn (PayrollLine $line): bool => $line->kind === PayComponentKind::Earning && $line->insurable);

        if (isset($state->snapshot['taxable_base'])) {
            $taxable = $state->snapshot['taxable_base'];
        }

        if (isset($state->snapshot['insurable_base'])) {
            $insurable = $state->snapshot['insurable_base'];
        }

        usort($state->lines, fn (PayrollLine $a, PayrollLine $b): int => [$this->kindOrder($a->kind), $a->displayOrder] <=> [$this->kindOrder($b->kind), $b->displayOrder]);

        $snapshot = [
            ...$inputs->toArray(),
            'run_type' => $type->value,
            'period' => ['id' => $period->id, 'code' => $period->code, 'start' => $period->start_date->toDateString(), 'end' => $period->end_date->toDateString(), 'periods_per_year' => $period->periodsPerYear()],
            'contexts' => $state->snapshot['contexts'] ?? [],
            'adjustments' => $adjustments->map(fn (PayrollAdjustment $row): array => ['id' => $row->id, 'reference' => $row->reference, 'amount' => (string) $row->amount, 'code' => $row->code])->values()->all(),
        ];
        $ruleVersions = $this->ruleVersions($inputs, $state);
        $lines = array_map(fn (PayrollLine $line): array => $line->toArray(), $state->lines);

        return new PayrollResult(
            lines: $state->lines,
            grossPay: $gross,
            totalDeductions: $deductions,
            employerContributions: $employer,
            netPay: $net,
            taxableBase: PayrollMath::round($taxable, 2),
            insurableBase: PayrollMath::round($insurable, 2),
            basicSalary: $state->basic,
            payBasis: $state->basis,
            prorationFactor: bcadd($state->factor, '0', 6),
            inputs: $snapshot,
            quantities: $state->quantities,
            ruleVersions: $ruleVersions,
            warnings: $state->warnings,
            loanRecoveries: $state->loanRecoveries,
            adjustmentIds: $adjustments->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            hash: hash('sha256', (string) json_encode([$snapshot, $state->quantities, $ruleVersions, $lines, [$gross, $deductions, $employer, $net]])),
        );
    }

    /**
     * Basic, structure lines, entries and encashment of a salaried run.
     */
    private function salary(Employee $employee, PayrollPeriod $period, EmployeePayrollInputs $inputs, object $state, ?string $leavingDate): void
    {
        $start = $period->start_date->toImmutable();
        $end = $period->end_date->toImmutable();
        $leaving = $employee->termination_date?->toImmutable() ?? ($leavingDate !== null ? CarbonImmutable::parse($leavingDate) : null);
        $windowFrom = $this->later($start, $employee->hire_date?->toImmutable());
        $windowTo = $this->earlier($end, $leaving);

        if ($windowTo->lessThan($windowFrom)) {
            $this->warn($state, 'not_employed', [], true);

            return;
        }

        if ($windowFrom->greaterThan($start) || $windowTo->lessThan($end)) {
            $this->warn($state, 'partial_period', ['from' => $windowFrom->toDateString(), 'to' => $windowTo->toDateString()]);
        }

        $segments = $this->clipSegments($inputs->salarySegments, $windowFrom, $windowTo);
        $state->basis = $inputs->payBasis;
        $state->basic = $inputs->basicSalary;

        if ($segments === []) {
            $this->warn($state, 'missing_salary', [], false);
        }

        $rules = $inputs->structure?->lines;

        if ($rules === null) {
            $this->warn($state, 'no_structure');
            $rules = array_values(array_filter($inputs->catalogue, fn (ComponentRule $rule): bool => $rule->calculationType === PayCalculationType::Basic));
        }

        $sensitive = array_values(array_filter($rules, fn (ComponentRule $rule): bool => $rule->calculationType === PayCalculationType::Basic || $rule->calculationType->isQuantityBased()));
        $others = array_values(array_filter($rules, fn (ComponentRule $rule): bool => in_array($rule->calculationType, [PayCalculationType::Fixed, PayCalculationType::Variable, PayCalculationType::Percentage], true)));

        $amounts = [];
        $parts = [];
        $quantities = [];
        $units = [];
        $totalBasic = '0';
        $totalFactor = '0';
        $paidDays = 0;
        $periodDays = (int) $start->diffInDays($end) + 1;
        $lastRates = ['day' => '0', 'hour' => '0', 'minute' => '0'];
        $contexts = [];
        $totals = ['late_minutes' => '0', 'early_leave_minutes' => '0', 'absent_days' => '0', 'unpaid_leave_days' => '0', 'overtime' => []];
        $attendanceTotals = [];
        $unpaidBreakdown = [];

        foreach ($segments as $index => $segment) {
            $isLast = $index === count($segments) - 1;
            $rates = $this->rates->for($segment->basicSalary, $segment->payBasis);
            $lastRates = $rates;
            $attendance = $this->attendance->between($employee->id, $segment->from, $segment->to);
            $unpaid = $this->leave->unpaidDays($employee, CarbonImmutable::parse($segment->from), CarbonImmutable::parse($segment->to));
            $unpaidBreakdown = [...$unpaidBreakdown, ...$this->leave->unpaidBreakdown($employee, CarbonImmutable::parse($segment->from), CarbonImmutable::parse($segment->to))->all()];
            $segmentDays = (int) CarbonImmutable::parse($segment->from)->diffInDays(CarbonImmutable::parse($segment->to)) + 1;
            $factor = $this->proration->factor($start, $end, $segment->from, $segment->to);
            [$activeDays] = $this->proration->fraction($start, $end, $segment->from, $segment->to);
            $overtime = array_map(fn (array $row): array => ['hours' => $row['hours'], 'multiplier' => $row['multiplier']], $attendance['overtime']);

            [$basic, $basicPart, $quantity, $factorUsed] = match ($segment->payBasis) {
                // Exact: salary × days ÷ period days (division last), rounded once on the BASIC line.
                PayBasis::Monthly => [
                    PayrollMath::fraction($segment->basicSalary, (string) $activeDays, (string) $periodDays),
                    $factor === '1'
                        ? ['key' => 'basic_full', 'params' => ['salary' => Money::format($segment->basicSalary)]]
                        : ['key' => 'basic_monthly', 'params' => ['salary' => Money::format($segment->basicSalary), 'days' => $segmentDays, 'period_days' => $periodDays, 'from' => $segment->from, 'to' => $segment->to]],
                    ['late_minutes' => $attendance['late_minutes'], 'early_leave_minutes' => $attendance['early_leave_minutes'], 'absent_days' => $attendance['absent_days'], 'unpaid_leave_days' => $unpaid],
                    $factor,
                ],
                PayBasis::Fixed => [
                    $isLast ? $segment->basicSalary : '0',
                    ['key' => $isLast ? 'basic_fixed' : 'basic_fixed_replaced', 'params' => ['salary' => Money::format($segment->basicSalary), 'from' => $segment->from, 'to' => $segment->to]],
                    ['late_minutes' => '0', 'early_leave_minutes' => '0', 'absent_days' => '0', 'unpaid_leave_days' => '0'],
                    $isLast ? '1' : '0',
                ],
                PayBasis::Daily => (function () use ($attendance, $unpaid, $rates, $segment): array {
                    $paidLeave = PayrollMath::max('0', bcsub($attendance['leave_days'], $unpaid, PayrollMath::SCALE));
                    $paidDays = bcadd(bcadd($attendance['attended_days'], $attendance['holiday_days'], PayrollMath::SCALE), $paidLeave, PayrollMath::SCALE);

                    return [
                        PayrollMath::mul($rates['day'], $paidDays),
                        ['key' => 'basic_daily', 'params' => ['days' => PayrollMath::trim(PayrollMath::round($paidDays, 2)), 'rate' => Money::format(PayrollMath::round($rates['day'], 2)), 'from' => $segment->from, 'to' => $segment->to]],
                        ['late_minutes' => $attendance['late_minutes'], 'early_leave_minutes' => $attendance['early_leave_minutes'], 'absent_days' => '0', 'unpaid_leave_days' => '0'],
                        '1',
                    ];
                })(),
                PayBasis::Hourly => (function () use ($attendance, $rates, $segment): array {
                    $hours = bcdiv($attendance['worked_minutes'], '60', PayrollMath::SCALE);

                    return [
                        PayrollMath::mul($rates['hour'], $hours),
                        ['key' => 'basic_hourly', 'params' => ['hours' => PayrollMath::trim(PayrollMath::round($hours, 2)), 'rate' => Money::format(PayrollMath::round($rates['hour'], 2)), 'from' => $segment->from, 'to' => $segment->to]],
                        ['late_minutes' => '0', 'early_leave_minutes' => '0', 'absent_days' => '0', 'unpaid_leave_days' => '0'],
                        '1',
                    ];
                })(),
            };

            $segmentPaidDays = match (true) {
                $segment->payBasis === PayBasis::Monthly => $activeDays,
                bccomp($factorUsed, '0', PayrollMath::SCALE) === 0 => 0,
                default => $periodDays,
            };
            $paidDays += $segmentPaidDays;
            $context = new CalculationContext($basic, $rates['day'], $rates['hour'], $rates['minute'], $factorUsed, $quantity, $overtime, (string) $segmentPaidDays, (string) $periodDays);
            $contexts[] = ['segment' => $segment->toArray(), ...$context->toArray(), 'attendance' => array_diff_key($attendance, ['policy_version_ids' => true])];
            $totalBasic = bcadd($totalBasic, $basic, PayrollMath::SCALE);
            $totalFactor = bcadd($totalFactor, $factorUsed, PayrollMath::SCALE);
            $attendanceTotals[] = $attendance;

            foreach (['late_minutes', 'early_leave_minutes', 'absent_days', 'unpaid_leave_days'] as $key) {
                $totals[$key] = bcadd($totals[$key], $quantity[$key], 4);
            }

            $totals['overtime'] = [...$totals['overtime'], ...$attendance['overtime']];

            // Unrounded per segment; each line is rounded once after the segments are summed.
            foreach ($this->components->evaluate($sensitive, $context, round: false) as $code => $raw) {
                $rule = $this->ruleByCode($sensitive, $code);
                $amounts[$code] = bcadd($amounts[$code] ?? '0', $raw, PayrollMath::SCALE);
                $parts[$code] = [...($parts[$code] ?? []), ...$this->sensitiveExplanation($rule, $context, $this->components->finalise($rule, $raw), $basicPart, $attendance['overtime'])];

                if ($rule->calculationType->isQuantityBased()) {
                    $qty = $rule->calculationType === PayCalculationType::OvertimeHours
                        ? array_reduce($attendance['overtime'], fn (string $sum, array $row): string => bcadd($sum, $row['hours'], 4), '0')
                        : $context->quantity((string) $rule->calculationType->quantityKey());
                    $quantities[$code] = bcadd($quantities[$code] ?? '0', $qty, 4);
                    $units[$code] = $this->unitRate($rule, $context);
                }
            }
        }

        $state->factor = $segments === [] ? '0' : PayrollMath::min('1', $totalFactor);
        $state->snapshot['contexts'] = $contexts;
        $state->quantities['attendance'] = $this->mergeAttendance($attendanceTotals);
        $state->quantities['totals'] = $totals;
        $state->quantities['unpaid_leave'] = array_values($unpaidBreakdown);
        $state->quantities['policy_version_ids'] = array_values(array_unique(array_merge(...array_map(fn (array $row): array => $row['policy_version_ids'], $attendanceTotals ?: [['policy_version_ids' => []]]))));

        $this->attendanceWarnings($state, $attendanceTotals, $sensitive, $inputs->payBasis);

        foreach ($amounts as $code => $amount) {
            $rule = $this->ruleByCode($sensitive, $code);
            $amount = $amounts[$code] = $this->components->finalise($rule, $amount);
            $source = match (true) {
                $rule->calculationType === PayCalculationType::Basic => PayrollLineSource::Basic,
                $rule->calculationType === PayCalculationType::UnpaidLeaveDays => PayrollLineSource::Leave,
                default => PayrollLineSource::Attendance,
            };

            if ($rule->calculationType->isQuantityBased() && bccomp($amount, '0', 2) === 0) {
                continue;
            }

            $state->lines[] = PayrollLine::fromRule($rule, $source, $amount, $parts[$code], $quantities[$code] ?? null, $units[$code] ?? null);
        }

        $combined = new CalculationContext(
            $totalBasic, $lastRates['day'], $lastRates['hour'], $lastRates['minute'], $state->factor,
            ['late_minutes' => $totals['late_minutes'], 'early_leave_minutes' => $totals['early_leave_minutes'], 'absent_days' => $totals['absent_days'], 'unpaid_leave_days' => $totals['unpaid_leave_days']],
            [], (string) min($paidDays, $periodDays), (string) $periodDays,
        );
        $known = $amounts;

        foreach ($this->components->evaluate($others, $combined, $known) as $code => $amount) {
            $rule = $this->ruleByCode($others, $code);
            $known[$code] = $amount;
            $state->lines[] = PayrollLine::fromRule($rule, PayrollLineSource::Structure, $amount, $this->otherExplanation($rule, $combined, $known, $rules));
        }

        foreach ($inputs->entries as $entry) {
            $amount = $this->components->evaluateEntries([$entry], $combined, $inputs->periodStart, $inputs->periodEnd, $known)[$entry->rule->code] ?? '0';

            if (bccomp($amount, '0', 2) === 0) {
                continue;
            }

            $line = PayrollLine::fromRule($entry->rule, PayrollLineSource::Entry, $amount, $this->entryExplanation($entry, $combined, $known, $rules));
            $line->sourceType = 'pay_entry';
            $line->sourceId = $entry->entryId;
            $line->displayOrder = $entry->rule->displayOrder + 1;
            $state->lines[] = $line;
        }

        $this->encashmentLines($employee, $windowFrom, $windowTo, $lastRates['day'], $state);
    }

    /**
     * Leave encashment booked in the employment window: days × day rate, as an earning.
     */
    private function encashmentLines(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, string $dayRate, object $state): void
    {
        $rows = $this->leave->encashments($employee, $from, $to);
        $state->quantities['encashments'] = $rows->values()->all();

        if ($rows->isEmpty()) {
            return;
        }

        $component = SalaryComponent::query()->where('code', (string) setting('payroll.leave_encashment_component'))->first();

        if ($component === null) {
            $this->warn($state, 'encashment_component_missing', ['code' => (string) setting('payroll.leave_encashment_component')], true);

            return;
        }

        $rule = ComponentRule::fromComponent($component);

        foreach ($rows as $row) {
            $amount = PayrollMath::round(PayrollMath::mul($row['days'], $dayRate), $rule->precision, $rule->rounding);
            $line = PayrollLine::fromRule($rule, PayrollLineSource::Encashment, $amount, [['key' => 'encashment', 'params' => [
                'days' => PayrollMath::trim($row['days']), 'type' => $row['leave_type'], 'date' => $row['date'], 'rate' => Money::format(PayrollMath::round($dayRate, 2)), 'amount' => Money::format($amount),
            ]]], $row['days'], $dayRate);
            $line->sourceType = 'leave_balance_transaction';
            $line->sourceId = $row['transaction_id'];
            $state->lines[] = $line;
        }
    }

    /**
     * Social insurance and income tax lines of the structure (bases configurable on the statutory version).
     */
    private function statutoryLines(EmployeePayrollInputs $inputs, PayrollPeriod $period, object $state): void
    {
        $rules = array_values(array_filter($inputs->structure?->lines ?? [], fn (ComponentRule $rule): bool => $rule->calculationType === PayCalculationType::Statutory));

        if ($rules === []) {
            return;
        }

        $earnings = fn (string $flag): string => $this->sumWhere($state->lines, fn (PayrollLine $line): bool => $line->kind === PayComponentKind::Earning && $line->{$flag});
        $reductions = fn (StatutoryRule $rule): string => $this->sumWhere($state->lines, fn (PayrollLine $line): bool => $line->kind === PayComponentKind::Deduction
            && ! in_array($line->source, [PayrollLineSource::Statutory, PayrollLineSource::Loan], true) && $rule->reducesBase($line->category->value, $line->code));

        $insurance = $inputs->statutoryRule(StatutoryType::SocialInsurance);
        $tax = $inputs->statutoryRule(StatutoryType::IncomeTax);
        $employeeInsurance = '0';
        $siRules = array_values(array_filter($rules, fn (ComponentRule $rule): bool => $rule->statutoryType === StatutoryType::SocialInsurance));
        $taxRules = array_values(array_filter($rules, fn (ComponentRule $rule): bool => $rule->statutoryType === StatutoryType::IncomeTax));

        if ($siRules !== []) {
            if ($insurance === null) {
                $this->warn($state, 'statutory_missing', ['type' => StatutoryType::SocialInsurance->value]);
            } else {
                $gross = $earnings('insurable');
                $reduced = $reductions($insurance);
                $base = PayrollMath::max('0', bcsub($gross, $reduced, PayrollMath::SCALE));
                $state->snapshot['insurable_base'] = $base;

                foreach ($siRules as $rule) {
                    $result = $this->statutory->socialInsurance($insurance, $base, $rule->precision);
                    $share = $rule->kind === PayComponentKind::EmployerContribution ? 'employer' : 'employee';
                    $rate = $share === 'employer' ? ($insurance->employerRate ?? '0') : ($insurance->employeeRate ?? '0');

                    if ($share === 'employee') {
                        $employeeInsurance = bcadd($employeeInsurance, $result['employee'], 2);
                    }

                    $line = PayrollLine::fromRule($rule, PayrollLineSource::Statutory, $result[$share], array_values(array_filter([
                        ['key' => 'insurance', 'params' => [
                            'gross' => Money::format(PayrollMath::round($gross, 2)), 'reduced' => Money::format(PayrollMath::round($reduced, 2)),
                            'base' => Money::format($result['base']), 'floor' => Money::format($insurance->salaryFloor), 'ceiling' => Money::format($insurance->salaryCeiling),
                            'rate' => PayrollMath::trim($rate), 'amount' => Money::format($result[$share]),
                        ]],
                        ['key' => 'statutory_version', 'params' => ['set' => $insurance->setCode, 'version' => $insurance->version, 'from' => $insurance->effectiveFrom]],
                        $insurance->isDemo ? ['key' => 'demo', 'params' => []] : null,
                    ])), $result['base'], $rate);
                    $line->sourceType = 'statutory_rule_version';
                    $line->sourceId = $insurance->versionId;
                    $state->lines[] = $line;
                }

                if ($insurance->isDemo) {
                    $this->warn($state, 'demo_statutory', ['set' => $insurance->setCode]);
                }
            }
        }

        if ($taxRules !== []) {
            if ($tax === null) {
                $this->warn($state, 'statutory_missing', ['type' => StatutoryType::IncomeTax->value]);

                return;
            }

            $gross = $earnings('taxable');
            $reduced = $reductions($tax);
            $insuranceReduction = $tax->deductEmployeeInsurance ? $employeeInsurance : '0';
            $taxable = PayrollMath::max('0', bcsub(bcsub($gross, $reduced, PayrollMath::SCALE), $insuranceReduction, PayrollMath::SCALE));
            $state->snapshot['taxable_base'] = $taxable;
            $periods = $period->periodsPerYear();

            foreach ($taxRules as $rule) {
                $amount = $this->statutory->incomeTax($tax, $taxable, $periods, $rule->precision);
                $annual = bcsub(bcmul($taxable, (string) $periods, PayrollMath::SCALE), $tax->annualExemption ?? '0', PayrollMath::SCALE);
                $line = PayrollLine::fromRule($rule, PayrollLineSource::Statutory, $amount, array_values(array_filter([
                    ['key' => 'tax', 'params' => [
                        'gross' => Money::format(PayrollMath::round($gross, 2)), 'reduced' => Money::format(PayrollMath::round($reduced, 2)),
                        'insurance' => Money::format($insuranceReduction), 'taxable' => Money::format(PayrollMath::round($taxable, 2)), 'periods' => $periods,
                        'exemption' => Money::format($tax->annualExemption ?? '0'), 'annual' => Money::format(PayrollMath::round(PayrollMath::max('0', $annual), 2)),
                        'annual_tax' => Money::format(PayrollMath::round($this->statutory->annualTax($tax, $annual), 2)), 'amount' => Money::format($amount),
                    ]],
                    ['key' => 'statutory_version', 'params' => ['set' => $tax->setCode, 'version' => $tax->version, 'from' => $tax->effectiveFrom]],
                    $tax->isDemo ? ['key' => 'demo', 'params' => []] : null,
                ])), PayrollMath::round($taxable, 2));
                $line->sourceType = 'statutory_rule_version';
                $line->sourceId = $tax->versionId;
                $state->lines[] = $line;
            }

            if ($tax->isDemo) {
                $this->warn($state, 'demo_statutory', ['set' => $tax->setCode]);
            }
        }
    }

    /**
     * Loan / advance instalments due; a final settlement recovers every scheduled instalment.
     */
    private function loanLines(Employee $employee, EmployeePayrollInputs $inputs, PayrollRunType $type, CarbonImmutable $start, object $state): void
    {
        $instalments = $type === PayrollRunType::FinalSettlement
            ? $this->loans->dueFor($employee, $start, '2999-12-31')
            : $inputs->loanInstalments;

        foreach ($instalments as $index => $instalment) {
            /** @var LoanInstalmentInput $instalment */
            $isAdvance = $instalment->loanType === LoanType::Advance;
            $explanation = [['key' => $type === PayrollRunType::FinalSettlement ? 'loan_final' : ($instalment->overdue ? 'loan_overdue' : 'loan'), 'params' => [
                'reference' => $instalment->loanReference, 'sequence' => $instalment->sequence, 'date' => $instalment->dueDate, 'amount' => Money::format($instalment->amount),
            ]]];

            $state->lines[] = new PayrollLine(
                code: $isAdvance ? 'ADVANCE' : 'LOAN',
                nameAr: __('payroll_runs.lines.'.($isAdvance ? 'advance' : 'loan'), ['reference' => $instalment->loanReference], 'ar'),
                nameEn: __('payroll_runs.lines.'.($isAdvance ? 'advance' : 'loan'), ['reference' => $instalment->loanReference], 'en'),
                kind: PayComponentKind::Deduction,
                category: $isAdvance ? PayComponentCategory::Advance : PayComponentCategory::Loan,
                source: PayrollLineSource::Loan,
                amount: bcadd($instalment->amount, '0', 2),
                explanation: $explanation,
                displayOrder: 900 + $index,
                sourceType: 'loan_instalment',
                sourceId: $instalment->instalmentId,
            );
            $state->loanRecoveries[] = ['instalment_id' => $instalment->instalmentId, 'amount' => bcadd($instalment->amount, '0', 2)];
        }
    }

    /**
     * Net pay is negative: recover less of the loans (latest instalment first) — the rest stays scheduled.
     */
    private function capLoans(object $state, string $net): string
    {
        $shortfall = bcmul($net, '-1', 2);

        for ($index = count($state->lines) - 1; $index >= 0 && bccomp($shortfall, '0', 2) > 0; $index--) {
            $line = $state->lines[$index];

            if ($line->source !== PayrollLineSource::Loan || bccomp($line->amount, '0', 2) <= 0) {
                continue;
            }

            $cut = PayrollMath::min($line->amount, $shortfall);
            $due = $line->amount;
            $line->amount = bcsub($line->amount, $cut, 2);
            $line->explanation[] = ['key' => 'loan_partial', 'params' => ['amount' => Money::format($line->amount), 'due' => Money::format($due)]];
            $shortfall = bcsub($shortfall, $cut, 2);

            foreach ($state->loanRecoveries as $key => $recovery) {
                if ($recovery['instalment_id'] === $line->sourceId) {
                    $state->loanRecoveries[$key]['amount'] = $line->amount;
                }
            }
        }

        $state->loanRecoveries = array_values(array_filter($state->loanRecoveries, fn (array $row): bool => bccomp($row['amount'], '0', 2) > 0));
        $this->warn($state, 'loan_partial');

        return bcadd(bcmul($shortfall, '-1', 2), '0', 2);
    }

    private function adjustmentLine(PayrollAdjustment $adjustment): PayrollLine
    {
        $period = $adjustment->originalPeriod;

        return new PayrollLine(
            code: $adjustment->code,
            nameAr: $adjustment->name_ar,
            nameEn: $adjustment->name_en,
            kind: $adjustment->kind,
            category: $adjustment->category,
            source: PayrollLineSource::Correction,
            amount: bcadd((string) $adjustment->amount, '0', 2),
            componentId: $adjustment->salary_component_id,
            quantity: $adjustment->quantity_delta === null ? null : (string) $adjustment->quantity_delta,
            rate: $adjustment->rate === null ? null : (string) $adjustment->rate,
            taxable: $adjustment->kind === PayComponentKind::Earning,
            insurable: false,
            explanation: [['key' => 'correction', 'params' => [
                'reference' => $adjustment->reference, 'period' => $period?->code ?? '—', 'reason' => $adjustment->reason,
            ]]],
            displayOrder: 800,
            sourceType: 'payroll_adjustment',
            sourceId: $adjustment->id,
        );
    }

    /**
     * @param  list<SalarySegment>  $segments
     * @return list<SalarySegment>
     */
    private function clipSegments(array $segments, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $clipped = [];

        foreach ($segments as $segment) {
            $segmentFrom = $this->later(CarbonImmutable::parse($segment->from), $from);
            $segmentTo = $this->earlier(CarbonImmutable::parse($segment->to), $to);

            if ($segmentTo->lessThan($segmentFrom)) {
                continue;
            }

            $clipped[] = new SalarySegment($segment->salaryId, $segmentFrom->toDateString(), $segmentTo->toDateString(), $segment->basicSalary, $segment->payBasis, $segment->currency);
        }

        return $clipped;
    }

    /**
     * @param  list<ComponentRule>  $sensitive
     * @param  list<array<string, mixed>>  $attendance
     */
    private function attendanceWarnings(object $state, array $attendance, array $sensitive, ?PayBasis $basis): void
    {
        $sum = fn (string $key): int => array_sum(array_map(fn (array $row): int => (int) $row[$key], $attendance));
        $usesAttendance = collect($sensitive)->contains(fn (ComponentRule $rule): bool => $rule->calculationType->isQuantityBased());

        if ($sum('overtime_pending_days') > 0) {
            $this->warn($state, 'overtime_pending', ['days' => $sum('overtime_pending_days')]);
        }

        if ($sum('mobile_pending_review') > 0) {
            $this->warn($state, 'mobile_pending_review', ['count' => $sum('mobile_pending_review')]);
        }

        if ($sum('incomplete_days') > 0) {
            $this->warn($state, 'incomplete_days', ['days' => $sum('incomplete_days')]);
        }

        if ($sum('adjusted_days') > 0) {
            $this->warn($state, 'locked_adjustments', ['days' => $sum('adjusted_days')]);
        }

        if ($usesAttendance && $sum('days') === 0 && in_array($basis, [PayBasis::Monthly, PayBasis::Daily, PayBasis::Hourly], true)) {
            $this->warn($state, 'no_attendance');
        }

        if ($basis === PayBasis::Fixed) {
            $this->warn($state, 'fixed_basis');
        }

        $moved = collect($state->quantities['placement'] ?? [])->map(fn (array $row): string => $row['branch_id'].'-'.$row['department_id'])->unique()->count() > 1;

        if ($moved) {
            $this->warn($state, 'moved_in_period');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function mergeAttendance(array $rows): array
    {
        $merged = ['days' => 0, 'late_minutes' => '0', 'early_leave_minutes' => '0', 'worked_minutes' => '0', 'absent_days' => '0', 'attended_days' => '0',
            'holiday_days' => '0', 'leave_days' => '0', 'incomplete_days' => 0, 'overtime' => [], 'overtime_pending_days' => 0, 'mobile_pending_review' => 0, 'adjusted_days' => 0];

        foreach ($rows as $row) {
            foreach ($merged as $key => $value) {
                $merged[$key] = match (true) {
                    $key === 'overtime' => [...$value, ...$row['overtime']],
                    is_int($value) => $value + (int) $row[$key],
                    default => bcadd($value, (string) $row[$key], 4),
                };
            }
        }

        return $merged;
    }

    /**
     * @param  array{key: string, params: array<string, mixed>}  $basicPart
     * @param  list<array{kind: string, hours: string, multiplier: string, policy_version_id: ?int}>  $overtimeRows
     * @return list<array{key: string, params: array<string, string|int>}>
     */
    private function sensitiveExplanation(ComponentRule $rule, CalculationContext $context, string $amount, array $basicPart, array $overtimeRows): array
    {
        if ($rule->calculationType === PayCalculationType::Basic) {
            return [$basicPart];
        }

        if ($rule->calculationType === PayCalculationType::OvertimeHours) {
            return array_map(function (array $row) use ($rule, $context): array {
                $multiplier = $rule->usePolicyMultiplier ? $row['multiplier'] : ($rule->rate ?? (string) setting('payroll.overtime_rate_multiplier'));
                $unit = $rule->amount ?? PayrollMath::mul($context->hourRate, $multiplier);

                return ['key' => $rule->amount !== null ? 'overtime_fixed' : 'overtime', 'params' => [
                    'hours' => PayrollMath::trim($row['hours']), 'kind' => $this->both(fn (string $locale): string => __('payroll_runs.overtime_kinds.'.$row['kind'], [], $locale)), 'hour_rate' => Money::format(PayrollMath::round($context->hourRate, 2)),
                    'multiplier' => PayrollMath::trim($multiplier), 'rate' => Money::format(PayrollMath::round($unit, 2)),
                    'amount' => Money::format(PayrollMath::round(PayrollMath::mul($row['hours'], $unit), 2)),
                ]];
            }, $overtimeRows);
        }

        $key = (string) $rule->calculationType->quantityKey();

        return [['key' => $key, 'params' => [
            'quantity' => PayrollMath::trim(bcadd($context->quantity($key), '0', 4)),
            'rate' => Money::format(PayrollMath::round($this->unitRate($rule, $context), 2)),
            'amount' => Money::format($amount),
        ]]];
    }

    private function unitRate(ComponentRule $rule, CalculationContext $context): string
    {
        return match ($rule->calculationType) {
            PayCalculationType::LateMinutes, PayCalculationType::EarlyLeaveMinutes => $rule->amount ?? PayrollMath::mul($context->minuteRate, $rule->rate ?? '1'),
            PayCalculationType::AbsentDays, PayCalculationType::UnpaidLeaveDays => $rule->amount ?? PayrollMath::mul($context->dayRate, $rule->rate ?? '1'),
            PayCalculationType::OvertimeHours => $rule->amount ?? $context->hourRate,
            default => '0',
        };
    }

    /**
     * @param  array<string, string>  $known
     * @param  list<ComponentRule>  $rules
     * @return list<array{key: string, params: array<string, string|int>}>
     */
    private function otherExplanation(ComponentRule $rule, CalculationContext $context, array $known, array $rules): array
    {
        if ($rule->calculationType === PayCalculationType::Percentage) {
            $baseRule = $rule->baseCode === null ? null : $this->ruleByCode($rules, $rule->baseCode);
            $baseName = $this->both(fn (string $locale): string => $rule->baseCode === null ? __('payroll.common.basic_salary', [], $locale) : ($baseRule?->name($locale) ?? (string) $rule->baseCode));
            $base = $rule->baseCode === null ? $context->basic : ($known[$rule->baseCode] ?? '0');

            return [['key' => 'percentage', 'params' => ['rate' => PayrollMath::trim((string) $rule->rate), 'base' => $baseName, 'base_amount' => Money::format(PayrollMath::round($base, 2))]]];
        }

        $amount = (string) ($rule->amount ?? '0');

        return [$rule->prorate && $context->prorationFactor !== '1'
            ? ['key' => 'fixed_prorated', 'params' => ['amount' => Money::format($amount), 'factor' => PayrollMath::trim(PayrollMath::round($context->prorationFactor, 4))]]
            : ['key' => 'fixed', 'params' => ['amount' => Money::format($amount)]]];
    }

    /**
     * @param  array<string, string>  $known
     * @param  list<ComponentRule>  $rules
     * @return list<array{key: string, params: array<string, string|int>}>
     */
    private function entryExplanation(PayEntryInput $entry, CalculationContext $context, array $known, array $rules): array
    {
        $parts = [match ($entry->recurrence) {
            PayEntryRecurrence::Instalment => ['key' => 'entry_instalment', 'params' => ['reference' => $entry->reference, 'number' => (int) $entry->instalmentNumber, 'count' => (int) $entry->instalmentCount]],
            default => ['key' => 'entry', 'params' => [
                'reference' => $entry->reference,
                'recurrence' => $this->both(fn (string $locale): string => __('payroll.enums.recurrences.'.$entry->recurrence->value, [], $locale)),
                'target' => $this->both(fn (string $locale): string => __('payroll.enums.entry_targets.'.$entry->target->value, [], $locale)),
            ]],
        }];

        return [...$parts, ...$this->otherExplanation($entry->rule, $context, $known, $rules)];
    }

    /**
     * @param  list<ComponentRule>  $rules
     */
    private function ruleByCode(array $rules, string $code): ?ComponentRule
    {
        foreach ($rules as $rule) {
            if ($rule->code === $code) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * @param  list<PayrollLine>  $lines
     * @return array{0: string, 1: string, 2: string, 3: string} gross, deductions, employer contributions, net
     */
    private function totals(array $lines): array
    {
        $gross = $this->sumWhere($lines, fn (PayrollLine $line): bool => $line->kind === PayComponentKind::Earning);
        $deductions = $this->sumWhere($lines, fn (PayrollLine $line): bool => $line->kind === PayComponentKind::Deduction);
        $employer = $this->sumWhere($lines, fn (PayrollLine $line): bool => $line->kind === PayComponentKind::EmployerContribution);

        return [bcadd($gross, '0', 2), bcadd($deductions, '0', 2), bcadd($employer, '0', 2), bcsub($gross, $deductions, 2)];
    }

    /**
     * @param  list<PayrollLine>  $lines
     */
    private function sumWhere(array $lines, callable $filter): string
    {
        return array_reduce(array_filter($lines, $filter), fn (string $sum, PayrollLine $line): string => bcadd($sum, $line->amount, 2), '0.00');
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleVersions(EmployeePayrollInputs $inputs, object $state): array
    {
        return [
            'salary_ids' => array_map(fn (SalarySegment $segment): int => $segment->salaryId, $inputs->salarySegments),
            'structure' => $inputs->structure === null ? null : [
                'structure_id' => $inputs->structure->structureId, 'code' => $inputs->structure->code, 'version_id' => $inputs->structure->versionId,
                'version' => $inputs->structure->version, 'assignment_id' => $inputs->structure->assignmentId, 'scope' => $inputs->structure->scope->value,
            ],
            'statutory' => array_map(fn (StatutoryRule $rule): array => [
                'set' => $rule->setCode, 'version_id' => $rule->versionId, 'version' => $rule->version, 'is_demo' => $rule->isDemo,
            ], $inputs->statutory),
            'entries' => array_map(fn (PayEntryInput $entry): int => $entry->entryId, $inputs->entries),
            'attendance_policy_version_ids' => $state->quantities['policy_version_ids'] ?? [],
        ];
    }

    private function warn(object $state, string $code, array $params = [], bool $blocking = false): void
    {
        foreach ($state->warnings as $warning) {
            if ($warning['code'] === $code && $warning['params'] === $params) {
                return;
            }
        }

        $state->warnings[] = ['code' => $code, 'params' => $params, 'blocking' => $blocking];
    }

    /**
     * A parameter in both languages ({ar, en}); PayrollItemLine renders the viewer's one.
     *
     * @return array{ar: string, en: string}
     */
    private function both(callable $text): array
    {
        return ['ar' => $text('ar'), 'en' => $text('en')];
    }

    private function kindOrder(PayComponentKind $kind): int
    {
        return match ($kind) {
            PayComponentKind::Earning => 0,
            PayComponentKind::Deduction => 1,
            PayComponentKind::EmployerContribution => 2,
        };
    }

    private function later(CarbonImmutable $a, ?CarbonImmutable $b): CarbonImmutable
    {
        return $b !== null && $b->greaterThan($a) ? $b->startOfDay() : $a;
    }

    private function earlier(CarbonImmutable $a, ?CarbonImmutable $b): CarbonImmutable
    {
        return $b !== null && $b->lessThan($a) ? $b->startOfDay() : $a;
    }
}
