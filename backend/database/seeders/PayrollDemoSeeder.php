<?php

namespace Database\Seeders;

use App\Actions\Payroll\AssignSalaryStructure;
use App\Actions\Payroll\ManageLoan;
use App\Actions\Payroll\ManagePayEntry;
use App\Actions\Payroll\SaveSalaryComponent;
use App\Actions\Payroll\SaveSalaryStructureVersion;
use App\Actions\Payroll\SaveStatutoryRuleVersion;
use App\Enums\EmployeeStatus;
use App\Enums\LoanChangeType;
use App\Enums\LoanSource;
use App\Enums\PayComponentKind;
use App\Enums\SalaryStructureScope;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\JobGrade;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\StatutoryRuleSet;
use App\Models\User;
use App\Services\Payroll\LoanInstalments;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Development demo data of the payroll component layer, created through the
 * real actions (validation, approvals, ledger, audit):
 *
 * - components: basic, housing (25% of basic), transport, meal, performance
 *   bonus and penalty (approval required), commission, overtime (policy
 *   multipliers), late / early / absence / unpaid-leave deductions,
 *   social insurance (employee + employer) and income tax — the statutory
 *   tables are flagged "DEMO — not legal advice", with made-up round rates;
 * - structures: STD (company, two versions), MGT (grades G6–G8), OPS (branch ALX),
 *   plus one employee-specific assignment — the 60 employees keep their
 *   existing basic salaries (employee_salaries is the only source);
 * - allowances: a branch meal allowance with an employee override, a
 *   department-wide one-time bonus approved by Finance, a bulk commission
 *   batch, a pending performance bonus; deductions: an approved penalty, an
 *   instalment recovery, a pending penalty;
 * - six loans / advances: pending, active (June–August recovered before go-live,
 *   later instalments by the payroll runs of PayrollRunDemoSeeder), repaid early,
 *   rejected, withdrawn, active with a pending reschedule request;
 * - statutory versions carry their calculation base (attendance / leave deductions
 *   reduce both bases; penalties and the employee's insurance reduce taxable income).
 *
 * Sets payroll.country_code to EG so the demo statutory tables are used.
 */
class PayrollDemoSeeder extends Seeder
{
    /** @var array<string, SalaryComponent> */
    private array $components = [];

    private User $accountant;

    private User $finance;

    public function run(): void
    {
        if (SalaryComponent::query()->exists()) {
            return;
        }

        config(['queue.default' => 'sync']);
        $this->accountant = User::query()->where('email', 'payroll.accountant@hrmplus.test')->firstOrFail();
        $this->finance = User::query()->where('email', 'finance.officer@hrmplus.test')->firstOrFail();
        auth('admin')->setUser($this->accountant);

        app(SettingsService::class)->set('payroll.country_code', 'EG');

        $this->components();
        $this->statutory();
        $this->structures();
        $this->entries();
        $this->loans();

        auth('admin')->forgetUser();
        $this->command?->info('Payroll demo: '.count($this->components).' components, '.SalaryStructure::query()->count().' structures, '
            .EmployeeLoan::query()->count().' loans.');
    }

    private function components(): void
    {
        $save = app(SaveSalaryComponent::class);
        $rows = [
            ['BASIC', 'الراتب الأساسي', 'Basic salary', 'earning', 'basic', 'basic', [], true, true],
            ['HOUSING', 'بدل السكن', 'Housing allowance', 'earning', 'allowance', 'percentage', ['rate' => '25'], true, false],
            ['TRANSPORT', 'بدل الانتقال', 'Transport allowance', 'earning', 'allowance', 'fixed', ['amount' => '500'], true, false],
            ['MEAL', 'بدل الوجبات', 'Meal allowance', 'earning', 'allowance', 'fixed', ['amount' => '300'], false, false],
            ['PERF_BONUS', 'مكافأة الأداء', 'Performance bonus', 'earning', 'bonus', 'variable', ['requires_approval' => true], true, false],
            ['COMMISSION', 'العمولة', 'Sales commission', 'earning', 'commission', 'variable', [], true, false],
            ['OVERTIME', 'العمل الإضافي', 'Overtime', 'earning', 'overtime', 'overtime_hours', ['use_policy_multiplier' => true], true, false],
            ['LATE', 'استقطاع التأخير', 'Late arrival deduction', 'deduction', 'attendance', 'late_minutes', ['rate' => '1'], false, false],
            ['EARLY', 'استقطاع الانصراف المبكر', 'Early departure deduction', 'deduction', 'attendance', 'early_leave_minutes', ['rate' => '1'], false, false],
            ['ABSENCE', 'استقطاع الغياب', 'Absence deduction', 'deduction', 'attendance', 'absent_days', ['rate' => '1'], false, false],
            ['UNPAID', 'استقطاع الإجازة بدون أجر', 'Unpaid leave deduction', 'deduction', 'leave', 'unpaid_leave_days', ['rate' => '1'], false, false],
            ['PENALTY', 'جزاء', 'Penalty', 'deduction', 'penalty', 'variable', ['requires_approval' => true], false, false],
            ['RECOVERY', 'استرداد مبالغ', 'Recovery', 'deduction', 'other', 'variable', [], false, false],
            ['SI_EMP', 'التأمينات الاجتماعية (حصة الموظف)', 'Social insurance (employee)', 'deduction', 'insurance', 'statutory', ['statutory_type' => 'social_insurance'], false, false],
            ['SI_ER', 'التأمينات الاجتماعية (حصة صاحب العمل)', 'Social insurance (employer)', 'employer_contribution', 'insurance', 'statutory', ['statutory_type' => 'social_insurance', 'show_on_payslip' => false], false, false],
            ['TAX', 'ضريبة الدخل', 'Income tax', 'deduction', 'tax', 'statutory', ['statutory_type' => 'income_tax'], false, false],
            // Phase 7b: leave encashment lines (setting payroll.leave_encashment_component).
            ['LEAVE_ENCASH', 'صرف رصيد الإجازات', 'Leave encashment', 'earning', 'leave', 'variable', [], true, false],
        ];

        foreach ($rows as $index => [$code, $ar, $en, $kind, $category, $type, $extra, $taxable, $insurable]) {
            $this->components[$code] = $save->handle(null, [
                'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'kind' => $kind, 'category' => $category, 'calculation_type' => $type,
                'is_taxable' => $taxable, 'is_insurable' => $insurable || $code === 'HOUSING', 'prorate_partial_period' => true, 'requires_approval' => false,
                'show_on_payslip' => true, 'rounding' => 'half_up', 'display_order' => ($index + 1) * 10, 'is_active' => true, ...$extra,
            ]);
        }
    }

    private function statutory(): void
    {
        $save = app(SaveStatutoryRuleVersion::class);

        $insurance = StatutoryRuleSet::create([
            'code' => 'DEMO-SI-EG', 'name_ar' => 'التأمينات الاجتماعية (تجريبي)', 'name_en' => 'Social insurance (demo)', 'country_code' => 'EG',
            'type' => 'social_insurance', 'is_demo' => true, 'is_active' => true,
            'description' => 'Made-up round rates for development only. Replace with the rules confirmed by the client (blocker B7).',
        ]);
        $save->handle($insurance, ['effective_from' => '2025-01-01', 'employee_rate' => '10', 'employer_rate' => '15', 'salary_floor' => '2000', 'salary_ceiling' => '12000', 'notes' => 'Demo', 'base_reductions' => ['attendance', 'leave']], $this->accountant);
        $save->handle($insurance, ['effective_from' => '2026-01-01', 'employee_rate' => '10', 'employer_rate' => '15', 'salary_floor' => '2500', 'salary_ceiling' => '14000', 'notes' => 'Demo: new floor / ceiling', 'base_reductions' => ['attendance', 'leave']], $this->accountant);

        $tax = StatutoryRuleSet::create([
            'code' => 'DEMO-TAX-EG', 'name_ar' => 'ضريبة الدخل (تجريبي)', 'name_en' => 'Income tax (demo)', 'country_code' => 'EG',
            'type' => 'income_tax', 'is_demo' => true, 'is_active' => true,
            'description' => 'Made-up round brackets for development only. Replace with the rules confirmed by the client (blocker B7).',
        ]);
        $save->handle($tax, ['effective_from' => '2025-01-01', 'annual_exemption' => '20000', 'notes' => 'Demo',
            'base_reductions' => ['attendance', 'leave', 'penalty'], 'deduct_employee_insurance' => true, 'brackets' => [
                ['lower_bound' => '0', 'upper_bound' => '30000', 'rate' => '0'],
                ['lower_bound' => '30000', 'upper_bound' => '60000', 'rate' => '10'],
                ['lower_bound' => '60000', 'upper_bound' => '120000', 'rate' => '15'],
                ['lower_bound' => '120000', 'upper_bound' => null, 'rate' => '20'],
            ]], $this->accountant);
    }

    private function structures(): void
    {
        $save = app(SaveSalaryStructureVersion::class);
        $assign = app(AssignSalaryStructure::class);
        $id = fn (string $code): int => $this->components[$code]->id;
        $attendance = ['OVERTIME', 'LATE', 'EARLY', 'ABSENCE', 'UNPAID'];
        $statutory = ['SI_EMP', 'SI_ER', 'TAX'];
        $lines = function (array $overrides, array $codes) use ($id): array {
            return array_map(fn (string $code): array => ['salary_component_id' => $id($code), ...($overrides[$code] ?? [])], $codes);
        };

        $standard = $save->create(
            ['code' => 'STD', 'name_ar' => 'الهيكل القياسي الشهري', 'name_en' => 'Standard monthly', 'description' => 'Default structure of the company.', 'is_active' => true],
            ['effective_from' => '2024-01-01', 'notes' => 'Initial structure', 'lines' => $lines([], ['BASIC', 'HOUSING', 'TRANSPORT', ...$attendance, ...$statutory])],
            $this->accountant,
        );
        $save->addVersion($standard, ['effective_from' => '2026-07-01', 'notes' => 'Transport allowance raised to 600', 'lines' => $lines(['TRANSPORT' => ['amount' => '600']], ['BASIC', 'HOUSING', 'TRANSPORT', ...$attendance, ...$statutory])], $this->accountant);

        $management = $save->create(
            ['code' => 'MGT', 'name_ar' => 'هيكل الإدارة', 'name_en' => 'Management', 'description' => 'Senior grades: higher housing and transport, no overtime.', 'is_active' => true],
            ['effective_from' => '2024-01-01', 'lines' => $lines(['HOUSING' => ['rate' => '30'], 'TRANSPORT' => ['amount' => '1000']], ['BASIC', 'HOUSING', 'TRANSPORT', 'LATE', 'ABSENCE', 'UNPAID', ...$statutory])],
            $this->accountant,
        );

        $operations = $save->create(
            ['code' => 'OPS', 'name_ar' => 'هيكل العمليات الميدانية', 'name_en' => 'Field operations', 'description' => 'Alexandria branch: lower housing, higher transport.', 'is_active' => true],
            ['effective_from' => '2024-01-01', 'lines' => $lines(['HOUSING' => ['rate' => '20'], 'TRANSPORT' => ['amount' => '750']], ['BASIC', 'HOUSING', 'TRANSPORT', ...$attendance, ...$statutory])],
            $this->accountant,
        );

        $assign->handle($standard, SalaryStructureScope::Company, null, '2024-01-01', 'Company default', $this->accountant);

        foreach (JobGrade::query()->where('level', '>=', 6)->get() as $grade) {
            $assign->handle($management, SalaryStructureScope::JobGrade, $grade->id, '2024-01-01', 'Senior grades', $this->accountant);
        }

        $alexandria = Branch::query()->where('code', 'ALX')->first();

        if ($alexandria !== null) {
            $assign->handle($operations, SalaryStructureScope::Branch, $alexandria->id, '2024-01-01', 'Field branch', $this->accountant);
        }

        // An employee-specific structure (the backend engineer behind employee2@) from next month.
        $engineer = $this->employeeOf('employee2@hrmplus.test');

        if ($engineer !== null) {
            $assign->handle($management, SalaryStructureScope::Employee, $engineer->id, now()->startOfMonth()->addMonthNoOverflow()->toDateString(), 'Team lead from next month', $this->accountant);
        }
    }

    private function entries(): void
    {
        $entries = app(ManagePayEntry::class);
        $month = now()->startOfMonth();
        $alexandria = Branch::query()->where('code', 'ALX')->first();
        $sales = Department::query()->where('code', 'SAL-CAI')->first();
        $active = Employee::query()->where('status', EmployeeStatus::Active)->orderBy('employee_number')->get();

        if ($alexandria !== null) {
            $entries->create(PayComponentKind::Earning, [
                'salary_component_id' => $this->components['MEAL']->id, 'target_type' => 'branch', 'target_id' => $alexandria->id, 'calculation' => 'fixed',
                'amount' => '300', 'recurrence' => 'recurring', 'start_date' => '2026-01-01', 'reason' => 'Field meal allowance for the Alexandria branch',
            ], $this->accountant);

            $override = $active->firstWhere('branch_id', $alexandria->id);

            if ($override !== null) {
                $entries->create(PayComponentKind::Earning, [
                    'salary_component_id' => $this->components['MEAL']->id, 'target_type' => 'employee', 'target_id' => $override->id, 'calculation' => 'fixed',
                    'amount' => '450', 'recurrence' => 'recurring', 'start_date' => '2026-06-01', 'reason' => 'Night shifts: higher meal allowance (replaces the branch one)',
                ], $this->accountant);
            }
        }

        if ($sales !== null) {
            // Department-wide one-time bonus, approved by Finance.
            $bonus = $entries->create(PayComponentKind::Earning, [
                'salary_component_id' => $this->components['PERF_BONUS']->id, 'target_type' => 'department', 'target_id' => $sales->id, 'calculation' => 'fixed',
                'amount' => '1500', 'recurrence' => 'one_time', 'start_date' => $month->toDateString(), 'reason' => 'Q3 sales target reached',
            ], $this->accountant);
            $entries->decide($bonus, $this->finance, true, 'Budget confirmed');

            // Bulk commission for the Cairo sales team.
            $team = $active->where('department_id', $sales->id)->take(5)->pluck('id')->all();

            if ($team !== []) {
                $entries->bulk(PayComponentKind::Earning, [
                    'salary_component_id' => $this->components['COMMISSION']->id, 'calculation' => 'percentage', 'rate' => '5',
                    'recurrence' => 'one_time', 'start_date' => $month->toDateString(), 'reason' => 'October sales commission',
                ], $team, $this->accountant, 'October commission run');
            }
        }

        $pick = $active->values();

        // Pending performance bonus (awaits Finance).
        $entries->create(PayComponentKind::Earning, [
            'salary_component_id' => $this->components['PERF_BONUS']->id, 'target_type' => 'employee', 'target_id' => $pick[10]->id, 'calculation' => 'fixed',
            'amount' => '2000', 'recurrence' => 'one_time', 'start_date' => $month->toDateString(), 'reason' => 'Project delivered ahead of schedule',
        ], $this->accountant);

        // Approved penalty, an instalment recovery and a pending penalty.
        $penalty = $entries->create(PayComponentKind::Deduction, [
            'salary_component_id' => $this->components['PENALTY']->id, 'target_type' => 'employee', 'target_id' => $pick[20]->id, 'calculation' => 'fixed',
            'amount' => '250', 'recurrence' => 'one_time', 'start_date' => $month->toDateString(), 'reason' => 'Repeated late arrival after a written warning',
        ], $this->accountant);
        $entries->decide($penalty, $this->finance, true);

        $entries->create(PayComponentKind::Deduction, [
            'salary_component_id' => $this->components['RECOVERY']->id, 'target_type' => 'employee', 'target_id' => $pick[25]->id,
            'recurrence' => 'instalment', 'total_amount' => '1200', 'instalment_count' => 4, 'start_date' => $month->subMonthNoOverflow()->toDateString(),
            'reason' => 'Damaged company phone, recovered over four months',
        ], $this->accountant);

        $entries->create(PayComponentKind::Deduction, [
            'salary_component_id' => $this->components['PENALTY']->id, 'target_type' => 'employee', 'target_id' => $pick[30]->id, 'calculation' => 'percentage',
            'rate' => '2', 'recurrence' => 'one_time', 'start_date' => $month->toDateString(), 'reason' => 'Unjustified absence (two days)',
        ], $this->accountant);
    }

    private function loans(): void
    {
        $loans = app(ManageLoan::class);
        $instalments = app(LoanInstalments::class);
        $eligible = Employee::query()->where('status', EmployeeStatus::Active)->whereNotNull('basic_salary')
            ->where('hire_date', '<=', now()->subYear()->toDateString())->orderBy('employee_number')->get();
        $employee1 = $this->employeeOf('employee1@hrmplus.test');
        $employee2 = $this->employeeOf('employee2@hrmplus.test');
        $employee3 = $this->employeeOf('employee3@hrmplus.test');
        $others = $eligible->reject(fn (Employee $row): bool => in_array($row->id, [$employee1?->id, $employee2?->id, $employee3?->id], true))->values();
        $amount = fn (Employee $employee, string $multiple): string => bcmul((string) $employee->basic_salary, $multiple, 2);
        $user = fn (?Employee $employee): User => User::query()->where('employee_id', $employee?->id)->first() ?? $this->accountant;

        // 1. Active loan of employee2, started in June: June–August recovered by the payroll used before
        //    HRM Plus went live (no run in this system); from September the payroll runs recover it.
        if ($employee2 !== null) {
            $this->at('2026-05-10', function () use ($loans, $employee2, $amount, $user): void {
                $loan = $loans->request($employee2, ['type' => 'loan', 'amount' => $amount($employee2, '2'), 'instalments' => 12, 'start_month' => '2026-06', 'reason' => 'Car repair'], LoanSource::Portal, $user($employee2));
                $loans->decide($loan, $this->finance, true, 'Within limits');
            });
            $loan = EmployeeLoan::query()->where('employee_id', $employee2->id)->latest('id')->first();

            foreach ($loan->instalments()->where('due_date', '<', '2026-09-01')->get() as $row) {
                $this->at($row->due_date->endOfMonth()->toDateString(), fn () => $instalments->markDeducted($row, (string) $row->amount, null, $this->accountant));
            }
        }

        // 2. Pending loan of employee1 (portal).
        if ($employee1 !== null) {
            $loans->request($employee1, ['type' => 'loan', 'amount' => $amount($employee1, '1.5'), 'instalments' => 10, 'start_month' => now()->addMonthNoOverflow()->format('Y-m'), 'reason' => 'School fees'], LoanSource::Portal, $user($employee1));
        }

        // 3. Advance repaid early (employee3).
        if ($employee3 !== null) {
            $this->at('2026-08-05', function () use ($loans, $employee3, $amount, $user): void {
                $loan = $loans->request($employee3, ['type' => 'advance', 'amount' => $amount($employee3, '0.4'), 'instalments' => 1, 'start_month' => '2026-09', 'reason' => 'Family emergency'], LoanSource::Api, $user($employee3));
                $loans->decide($loan, $this->finance, true);
                $loans->repay($loan->refresh(), (string) $loan->outstanding_amount, '2026-08-25', 'Paid back in cash before payroll', $this->accountant);
            });
        }

        if ($others->count() < 3) {
            return;
        }

        // 4. Rejected loan (requested by HR on behalf).
        $rejected = $loans->request($others[0], ['type' => 'loan', 'amount' => $amount($others[0], '3'), 'instalments' => 24, 'start_month' => now()->addMonthNoOverflow()->format('Y-m'), 'reason' => 'Apartment down payment'], LoanSource::Admin, $this->accountant);
        $loans->decide($rejected, $this->finance, false, 'An existing salary advance must be settled first');

        // 5. Withdrawn advance request.
        $withdrawn = $loans->request($others[1], ['type' => 'advance', 'amount' => $amount($others[1], '0.3'), 'instalments' => 1, 'start_month' => now()->addMonthNoOverflow()->format('Y-m'), 'reason' => 'Travel'], LoanSource::Admin, $this->accountant);
        $loans->cancel($withdrawn, $this->accountant, 'Requested by mistake');

        // 6. Active loan with a pending reschedule request.
        $this->at('2026-09-03', function () use ($loans, $others, $amount): void {
            $loan = $loans->request($others[2], ['type' => 'loan', 'amount' => $amount($others[2], '1'), 'instalments' => 6, 'start_month' => '2026-10', 'reason' => 'Medical bills'], LoanSource::Admin, $this->accountant);
            $loans->decide($loan, $this->finance, true);
        });
        // Its October instalment is recovered by the October payroll run (PayrollRunDemoSeeder).
        $active = EmployeeLoan::query()->where('employee_id', $others[2]->id)->latest('id')->first();
        $loans->requestChange($active->refresh(), LoanChangeType::Reschedule, [
            'new_instalment_count' => 10, 'new_start_month' => now()->addMonthNoOverflow()->format('Y-m'), 'reason' => 'Lower monthly instalment after a family change',
        ], $this->accountant);
    }

    /**
     * Run a step as if today were the given date (requests must start this month or later).
     */
    private function at(string $date, callable $callback): void
    {
        Carbon::setTestNow(CarbonImmutable::parse($date.' 10:00:00'));

        try {
            $callback();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function employeeOf(string $email): ?Employee
    {
        $id = User::query()->where('email', $email)->value('employee_id');

        return $id ? Employee::query()->find($id) : null;
    }
}
