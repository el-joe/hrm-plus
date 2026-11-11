<?php

namespace App\Services\Payroll;

use App\Enums\PayBasis;
use App\Enums\PayCalculationType;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryComponent;
use App\Services\Payroll\Data\ComponentRule;
use App\Services\Payroll\Data\EmployeePayrollInputs;
use App\Services\Payroll\Data\SalarySegment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * The read API of the component layer for the payroll engine (Phase 7b).
 * Contract: docs/PAYROLL_ENGINE.md.
 *
 *     $inputs = app(PayrollInputs::class)->for($employee, '2026-11-01', '2026-11-30');
 *     $inputs->structure?->lines;   // ComponentRule[] of the structure version in force at the period end
 *     $inputs->entries;             // approved allowances / deductions after scope precedence
 *     $inputs->loanInstalments;     // scheduled loan instalments due (incl. overdue)
 *     $inputs->statutory;           // statutory rule versions in force at the period end
 *     $inputs->toArray();           // snapshot to store with the calculation
 *
 * Data scopes are ignored (payroll decides its own population). Nothing is
 * written. Attendance and leave quantities are NOT included: the engine reads
 * attendance_days and App\Services\Leave\LeavePayrollFeed itself and passes the
 * quantities to ComponentCalculator through a CalculationContext.
 */
class PayrollInputs
{
    public function __construct(
        private readonly SalaryStructureResolver $structures,
        private readonly PayEntryResolver $entries,
        private readonly LoanInstalments $loans,
        private readonly StatutoryRules $statutory,
        private readonly PayrollPlacement $placement,
    ) {}

    public function for(Employee $employee, CarbonInterface|string $periodStart, CarbonInterface|string $periodEnd): EmployeePayrollInputs
    {
        $start = CarbonImmutable::parse($periodStart)->startOfDay();
        $end = CarbonImmutable::parse($periodEnd)->startOfDay();

        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('The period ends before it starts.');
        }

        $segments = $this->salarySegments($employee, $start, $end);
        $current = $segments === [] ? null : $segments[array_key_last($segments)];
        $placement = $this->placement->on($employee, $end);
        $country = strtoupper((string) setting('payroll.country_code'));

        return new EmployeePayrollInputs(
            employeeId: $employee->id,
            periodStart: $start->toDateString(),
            periodEnd: $end->toDateString(),
            currency: (string) setting('localisation.currency_code'),
            countryCode: $country,
            basicSalary: $current?->basicSalary,
            payBasis: $current?->payBasis,
            salarySegments: $segments,
            structure: $this->structures->for($employee, $end),
            entries: $this->entries->for($employee, $start, $end),
            loanInstalments: $this->loans->dueFor($employee, $start, $end),
            statutory: $this->statutory->all($end, $country),
            catalogue: $this->catalogue(),
            branchId: $placement?->branch_id ?? $employee->branch_id,
            departmentId: $placement?->department_id ?? $employee->department_id,
            jobGradeId: $placement?->job_grade_id ?? $employee->job_grade_id,
            hireDate: $employee->hire_date?->toDateString(),
            terminationDate: $employee->termination_date?->toDateString(),
        );
    }

    /**
     * Basic salary rows covering the period, clipped to it, oldest first. Future-dated rows
     * that are not applied yet count from their effective date; cancelled rows never count.
     *
     * @return list<SalarySegment>
     */
    public function salarySegments(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = EmployeeSalary::query()->where('employee_id', $employee->id)->whereNull('cancelled_at')
            ->where('effective_date', '<=', $end->toDateString())
            ->orderBy('effective_date')->orderBy('id')->get()
            ->groupBy(fn (EmployeeSalary $row): string => $row->effective_date->toDateString())
            ->map(fn ($sameDay) => $sameDay->last())
            ->values();

        $segments = [];

        foreach ($rows as $index => $row) {
            $from = $row->effective_date->toImmutable();
            $next = $rows[$index + 1] ?? null;
            $to = $next ? $next->effective_date->toImmutable()->subDay() : $end;

            if ($to->lessThan($start)) {
                continue;
            }

            $segments[] = new SalarySegment(
                salaryId: $row->id,
                from: ($from->greaterThan($start) ? $from : $start)->toDateString(),
                to: ($to->lessThan($end) ? $to : $end)->toDateString(),
                basicSalary: (string) $row->basic_salary,
                payBasis: $row->pay_basis ?? PayBasis::Monthly,
                currency: $row->currency,
            );
        }

        return $segments;
    }

    /**
     * Active basic, quantity-based and statutory components by code: the rules the engine
     * applies to every employee whose structure includes them.
     *
     * @return array<string, ComponentRule>
     */
    private function catalogue(): array
    {
        $codes = SalaryComponent::query()->pluck('code', 'id')->all();

        return SalaryComponent::query()->active()->ordered()
            ->whereNotIn('calculation_type', [PayCalculationType::Fixed, PayCalculationType::Percentage, PayCalculationType::Variable])
            ->get()
            ->mapWithKeys(fn (SalaryComponent $component): array => [$component->code => ComponentRule::fromComponent($component, codes: $codes)])
            ->all();
    }
}
