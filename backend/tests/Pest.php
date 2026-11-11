<?php

use App\Actions\Leave\ManageLeaveRequest;
use App\Actions\Payroll\AssignSalaryStructure;
use App\Actions\Payroll\ManageLoan;
use App\Actions\Payroll\ManagePayEntry;
use App\Actions\Payroll\ManagePayrollPeriod;
use App\Actions\Payroll\ManagePayrollRun;
use App\Actions\Payroll\SaveSalaryStructureVersion;
use App\Actions\Payroll\SaveStatutoryRuleVersion;
use App\Enums\AttendanceScope;
use App\Enums\LeaveRequestSource;
use App\Enums\LoanSource;
use App\Enums\PayBasis;
use App\Enums\PayComponentKind;
use App\Enums\PayrollRunType;
use App\Enums\PunchDirection;
use App\Enums\RawEventSource;
use App\Enums\SalaryChangeType;
use App\Enums\SalaryStructureScope;
use App\Enums\ScheduleType;
use App\Enums\StatutoryType;
use App\Models\AttendanceDay;
use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyVersion;
use App\Models\AttendanceRawEvent;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\EmploymentType;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayEntry;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\ScheduleAssignment;
use App\Models\Shift;
use App\Models\StatutoryRuleSet;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceEngine;
use App\Services\Attendance\AttendancePolicyResolver;
use App\Services\Attendance\Data\RawEventData;
use App\Services\Attendance\RawEventRecorder;
use App\Services\Employees\EmploymentHistory;
use App\Services\Leave\LeaveAccrualService;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Payroll\Data\CalculationContext;
use App\Services\Payroll\SalaryRates;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Database\Seeders\AttendanceDefaultsSeeder;
use Database\Seeders\LeaveDefaultsSeeder;
use Database\Seeders\PayrollDefaultsSeeder;
use Database\Seeders\RecruitmentDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/

// Tests\TestCase refreshes the database for every test.
pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
|
| Admin accounts are only meaningful with roles, so these helpers seed the
| permission catalogue and the default roles on first use in a test.
|
*/

/**
 * Seed the permission catalogue and the default roles (once per test).
 */
function seedRoles(): void
{
    if (Permission::query()->doesntExist()) {
        test()->seed(RolesAndPermissionsSeeder::class);
    }
}

/**
 * An active admin holding the given default roles, e.g. adminWithRoles('Attendance Officer').
 */
function adminWithRoles(string ...$roles): User
{
    seedRoles();

    return User::factory()->admin()->withRoles(...$roles)->create();
}

function superAdmin(): User
{
    return adminWithRoles('Super Admin');
}

/**
 * An active admin with a custom role holding exactly the given permissions.
 */
function adminWithPermissions(string ...$permissions): User
{
    seedRoles();

    $role = Role::create(['name' => 'Test role '.uniqid(), 'guard_name' => 'admin']);
    $role->syncPermissions($permissions);

    return User::factory()->admin()->create()->assignRole($role);
}

/**
 * A small organisation for employee tests: a branch, a department in it, an
 * employment type and a position (headcount 2) in the department.
 *
 * @return array{branch: Branch, department: Department, type: EmploymentType, position: Position}
 */
function hrStructure(): array
{
    $branch = Branch::factory()->create(['code' => 'CAI', 'name_en' => 'Cairo']);
    $department = Department::factory()->create(['code' => 'IT', 'branch_id' => $branch->id, 'name_en' => 'Information technology']);
    $type = EmploymentType::factory()->create(['code' => 'FT']);
    $position = Position::factory()->create(['code' => 'DEV', 'department_id' => $department->id, 'headcount' => 2]);

    return compact('branch', 'department', 'type', 'position');
}

/**
 * Valid input of the employee create form for the given structure.
 *
 * @param  array{branch: Branch, department: Department, type: EmploymentType, position?: Position}  $structure
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function employeePayload(array $structure, array $overrides = []): array
{
    return [
        'name_ar' => 'سارة أحمد',
        'name_en' => 'Sara Ahmed',
        'email' => 'sara.ahmed@example.test',
        'hire_date' => '2024-03-01',
        'branch_id' => $structure['branch']->id,
        'department_id' => $structure['department']->id,
        'employment_type_id' => $structure['type']->id,
        ...$overrides,
    ];
}

/**
 * Phase 4 reference data: default pipeline, "job_offer" approval workflow,
 * the general evaluation form and the default onboarding checklist.
 */
function seedRecruitment(): void
{
    test()->seed(RecruitmentDefaultsSeeder::class);
}

/**
 * A fake PDF upload for CV fields.
 */
function fakeCv(string $name = 'cv.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 120, 'application/pdf');
}

/*
|--------------------------------------------------------------------------
| Attendance helpers (Phase 5a)
|--------------------------------------------------------------------------
*/

/**
 * Statuses, approval workflows and the company default policy.
 */
function seedAttendance(): void
{
    test()->seed(AttendanceDefaultsSeeder::class);
}

/**
 * An employee hired 2025-01-01 in a branch on Cairo time (UTC+2 in winter).
 */
function attendanceEmployee(array $attributes = []): Employee
{
    $branch = $attributes['branch_id'] ?? Branch::factory()->create(['timezone' => 'Africa/Cairo'])->id;
    $department = $attributes['department_id'] ?? Department::factory()->create(['branch_id' => $branch])->id;

    return Employee::factory()->create([
        'branch_id' => $branch, 'department_id' => $department, 'hire_date' => '2025-01-01', 'original_hire_date' => '2025-01-01', ...$attributes,
    ]);
}

/**
 * A weekly schedule: shifts by weekday (0 = Sunday); weekdays without shifts are rest days.
 *
 * @param  array<int, list<Shift>|Shift>  $days
 */
function weeklySchedule(array $days, string $code = 'WK'): WorkSchedule
{
    $schedule = WorkSchedule::create([
        'code' => $code.uniqid(), 'name_ar' => 'جدول', 'name_en' => 'Schedule '.$code, 'type' => ScheduleType::Weekly, 'is_active' => true,
    ]);

    foreach ($days as $weekday => $shifts) {
        foreach (is_array($shifts) ? $shifts : [$shifts] as $shift) {
            $schedule->days()->create(['day_index' => $weekday, 'shift_id' => $shift->id]);
        }
    }

    return $schedule;
}

/**
 * Sunday–Thursday with the given shift, Friday and Saturday off.
 */
function workWeek(Shift $shift): WorkSchedule
{
    return weeklySchedule([0 => $shift, 1 => $shift, 2 => $shift, 3 => $shift, 4 => $shift]);
}

function assignSchedule(WorkSchedule $schedule, AttendanceScope $scope, ?int $scopeId, string $from = '2025-01-01', ?string $to = null): ScheduleAssignment
{
    return ScheduleAssignment::create([
        'work_schedule_id' => $schedule->id, 'scope_type' => $scope, 'scope_id' => $scopeId, 'effective_from' => $from, 'effective_to' => $to,
    ]);
}

/**
 * A policy whose version 1 (from 2020-01-01) has the default rules plus the overrides, assigned to the scope.
 *
 * @param  array<string, mixed>  $rules
 */
function attendancePolicy(array $rules = [], AttendanceScope $scope = AttendanceScope::Company, ?int $scopeId = null): AttendancePolicy
{
    $policy = AttendancePolicy::create(['code' => 'P'.uniqid(), 'name_ar' => 'سياسة', 'name_en' => 'Policy', 'is_active' => true]);
    $defaults = app(AttendancePolicyResolver::class)->defaults()->only(AttendancePolicyVersion::RULES);
    $policy->versions()->create([...$defaults, 'late_grace_minutes' => 10, 'early_leave_grace_minutes' => 10, 'overtime_min_minutes' => 30,
        'overtime_requires_approval' => false, 'punch_dedupe_seconds' => 60, ...$rules, 'version' => 1, 'effective_from' => '2020-01-01']);
    $policy->assignments()->create(['scope_type' => $scope, 'scope_id' => $scopeId, 'effective_from' => '2020-01-01']);

    return $policy;
}

/**
 * Record a punch at a Cairo wall-clock time (no processing).
 */
function punchAt(Employee $employee, string $localDateTime, string $direction = 'in', string $source = 'device'): AttendanceRawEvent
{
    return app(RawEventRecorder::class)->record(RawEventData::punch(
        RawEventSource::from($source), $employee->id, Carbon::parse($localDateTime, 'Africa/Cairo'),
        PunchDirection::from($direction), 'Africa/Cairo', 'test:'.uniqid('', true),
    ), process: 'none');
}

/**
 * Process a date and return its days keyed by shift_key.
 *
 * @return Collection<int, AttendanceDay>
 */
function processDay(Employee $employee, string $date): Collection
{
    return collect(app(AttendanceEngine::class)->process($employee, $date))->keyBy('shift_key');
}

/*
|--------------------------------------------------------------------------
| Leave helpers (Phase 6)
|--------------------------------------------------------------------------
*/

/**
 * Roles, attendance statuses / policy and the leave approval workflows
 * (leave_request: leaves.approve then leaves.manage; leave_request_hr and
 * leave_cancellation: leaves.manage).
 */
function seedLeave(): void
{
    seedRoles();
    seedAttendance();
    test()->seed(LeaveDefaultsSeeder::class);
}

/**
 * Sunday–Thursday 08:00–16:00 for the whole company: Friday and Saturday are rest days.
 */
function leaveWorkWeek(): Shift
{
    $shift = Shift::factory()->between('08:00', '16:00')->create();
    assignSchedule(workWeek($shift), AttendanceScope::Company, null, '2015-01-01');

    return $shift;
}

/**
 * A leave type with one company policy (21 days up front unless overridden).
 *
 * @param  array<string, mixed>  $type
 * @param  array<string, mixed>  $policy
 */
function leaveTypeWithPolicy(array $type = [], array $policy = []): LeaveType
{
    $leaveType = LeaveType::factory()->create($type);
    LeavePolicy::factory()->create(['leave_type_id' => $leaveType->id, ...$policy]);

    return $leaveType;
}

function accrueLeave(string $date, ?int $employeeId = null): void
{
    app(LeaveAccrualService::class)->accrueAll(CarbonImmutable::parse($date), $employeeId);
}

function availableLeave(Employee $employee, LeaveType $type, ?int $year = null): string
{
    return app(LeaveBalanceCalculator::class)->for($employee, $type, $year)->available;
}

/**
 * Submit a request through the real action (default: filed by HR on the employee's behalf).
 *
 * @param  array<string, mixed>  $data
 * @param  list<UploadedFile>  $attachments
 */
function fileLeave(
    Employee $employee, LeaveType $type, string $start, ?string $end = null, array $data = [], ?User $by = null,
    LeaveRequestSource $source = LeaveRequestSource::Admin, array $attachments = [], bool $recordAsApproved = false,
): LeaveRequest {
    $by ??= adminWithRoles('HR Specialist');

    return app(ManageLeaveRequest::class)->submit(
        $employee, ['leave_type_id' => $type->id, 'start_date' => $start, 'end_date' => $end ?? $start, ...$data], $source, $by, $attachments, $recordAsApproved,
    );
}

/*
|--------------------------------------------------------------------------
| Payroll helpers (Phase 7a)
|--------------------------------------------------------------------------
*/

/**
 * Roles and the payroll approval workflows (payroll_allowance, payroll_deduction,
 * loan_request, loan_change — one level each).
 */
function seedPayroll(): void
{
    seedRoles();
    test()->seed(PayrollDefaultsSeeder::class);
}

/**
 * An active employee hired 2024-01-01 with a basic salary recorded through EmploymentHistory.
 *
 * @param  array<string, mixed>  $attributes
 */
function paidEmployee(string $salary = '6000.00', PayBasis $basis = PayBasis::Monthly, array $attributes = [], string $from = '2024-01-01'): Employee
{
    $employee = attendanceEmployee(['hire_date' => '2024-01-01', 'original_hire_date' => '2024-01-01', ...$attributes]);
    app(EmploymentHistory::class)->recordSalary($employee, $salary, SalaryChangeType::Initial, CarbonImmutable::parse($from), applyNow: true, payBasis: $basis);

    return $employee->refresh();
}

/**
 * A calculation context with rates derived from a monthly salary (30 days, 8 hours unless the settings say otherwise).
 *
 * @param  array<string, string>  $quantities
 * @param  list<array{hours: string, multiplier: string}>  $overtime
 */
function payContext(string $basic = '6000', string $factor = '1', array $quantities = [], array $overtime = [], PayBasis $basis = PayBasis::Monthly): CalculationContext
{
    $rates = app(SalaryRates::class)->for($basic, $basis);

    return new CalculationContext(bcmul($basic, $factor, 6), $rates['day'], $rates['hour'], $rates['minute'], $factor, $quantities, $overtime);
}

/**
 * A structure with version 1 from $from holding the given components (lines: component or [component, overrides]).
 *
 * @param  list<SalaryComponent|array{0: SalaryComponent, 1: array<string, mixed>}>  $lines
 */
function salaryStructure(string $code, array $lines, string $from = '2024-01-01'): SalaryStructure
{
    return app(SaveSalaryStructureVersion::class)->create(
        ['code' => $code, 'name_ar' => 'هيكل '.$code, 'name_en' => 'Structure '.$code, 'is_active' => true],
        ['effective_from' => $from, 'lines' => array_map(fn ($line): array => is_array($line)
            ? ['salary_component_id' => $line[0]->id, ...$line[1]]
            : ['salary_component_id' => $line->id], $lines)],
        null,
    );
}

/**
 * Create an allowance / deduction through the real action (by an accountant unless given).
 *
 * @param  array<string, mixed>  $data
 */
function payEntry(PayComponentKind $kind, SalaryComponent $component, array $data, ?User $by = null): PayEntry
{
    return app(ManagePayEntry::class)->create($kind, [
        'salary_component_id' => $component->id, 'calculation' => 'fixed', 'recurrence' => 'recurring', 'start_date' => '2026-01-01',
        'reason' => 'Test', ...$data,
    ], $by ?? adminWithRoles('Payroll Accountant'));
}

/**
 * Request a loan / advance through the real action (by the accountant on behalf unless given).
 *
 * @param  array<string, mixed>  $data
 */
function requestLoan(Employee $employee, array $data = [], ?User $by = null, LoanSource $source = LoanSource::Admin): EmployeeLoan
{
    return app(ManageLoan::class)->request($employee, [
        'type' => 'loan', 'amount' => '6000', 'instalments' => 6, 'start_month' => now()->addMonthNoOverflow()->format('Y-m'), 'reason' => 'Test', ...$data,
    ], $source, $by ?? adminWithRoles('Payroll Accountant'));
}

/*
|--------------------------------------------------------------------------
| Payroll run helpers (Phase 7b)
|--------------------------------------------------------------------------
|
| A small but complete payroll configuration with exact numbers: 30 working
| days a month, 8 hours a day, so a monthly salary of 6000.00 has a day rate
| of 200.00, an hour rate of 25.00 and a minute rate of 0.416667.
*/

/**
 * Components BASIC, HOUSING (25 % of basic), TRANSPORT (500 fixed, prorated), LATE (2.50 per
 * minute), EARLY (minute rate × 1), ABSENCE (day rate × 1), UNPAID (day rate × 1), OVERTIME
 * (policy multipliers), LEAVE_ENCASH, SI_EMP / SI_ER (social insurance) and TAX, one company
 * structure "STD" with all of them, and statutory tables for EG: social insurance 10 % / 15 %,
 * floor 2000, ceiling 12000, attendance and leave deductions reduce the insurable base; income
 * tax 0 % to 30000, 10 % to 60000, 20 % above, no exemption, the employee's insurance and
 * attendance deductions reduce taxable income.
 *
 * @param  array{statutory?: bool, structure?: bool}  $options
 * @return array<string, SalaryComponent>
 */
function payrollSetup(array $options = []): array
{
    seedPayroll();
    app(SettingsService::class)->set('payroll.country_code', 'EG');
    app(SettingsService::class)->set('payroll.working_days_per_month', 30);
    app(SettingsService::class)->set('payroll.working_hours_per_day', '8.00');

    $c = [
        'BASIC' => SalaryComponent::factory()->basic()->create(['display_order' => 1]),
        'HOUSING' => SalaryComponent::factory()->percentage('25')->create(['code' => 'HOUSING', 'display_order' => 2]),
        'TRANSPORT' => SalaryComponent::factory()->create(['code' => 'TRANSPORT', 'amount' => '500', 'display_order' => 3]),
        'OVERTIME' => SalaryComponent::factory()->create(['code' => 'OVERTIME', 'category' => 'overtime', 'calculation_type' => 'overtime_hours', 'amount' => null, 'use_policy_multiplier' => true, 'display_order' => 4]),
        'LATE' => SalaryComponent::factory()->deduction()->create(['code' => 'LATE', 'category' => 'attendance', 'calculation_type' => 'late_minutes', 'amount' => '2.50', 'display_order' => 10]),
        'EARLY' => SalaryComponent::factory()->deduction()->create(['code' => 'EARLY', 'category' => 'attendance', 'calculation_type' => 'early_leave_minutes', 'amount' => null, 'rate' => '1', 'display_order' => 11]),
        'ABSENCE' => SalaryComponent::factory()->deduction()->create(['code' => 'ABSENCE', 'category' => 'attendance', 'calculation_type' => 'absent_days', 'amount' => null, 'rate' => '1', 'display_order' => 12]),
        'UNPAID' => SalaryComponent::factory()->deduction()->create(['code' => 'UNPAID', 'category' => 'leave', 'calculation_type' => 'unpaid_leave_days', 'amount' => null, 'rate' => '1', 'display_order' => 13]),
        'LEAVE_ENCASH' => SalaryComponent::factory()->variable()->create(['code' => 'LEAVE_ENCASH', 'category' => 'leave', 'display_order' => 5]),
        'SI_EMP' => SalaryComponent::factory()->statutory(StatutoryType::SocialInsurance)->create(['code' => 'SI_EMP', 'category' => 'insurance', 'display_order' => 20]),
        'SI_ER' => SalaryComponent::factory()->statutory(StatutoryType::SocialInsurance)->create(['code' => 'SI_ER', 'kind' => 'employer_contribution', 'category' => 'insurance', 'show_on_payslip' => false, 'display_order' => 21]),
        'TAX' => SalaryComponent::factory()->statutory(StatutoryType::IncomeTax)->create(['code' => 'TAX', 'category' => 'tax', 'display_order' => 22]),
    ];

    if ($options['structure'] ?? true) {
        $lines = array_values(array_filter($c, fn (SalaryComponent $component): bool => $component->code !== 'LEAVE_ENCASH'
            && (($options['statutory'] ?? true) || $component->calculation_type->value !== 'statutory')));
        $structure = salaryStructure('STD', $lines);
        app(AssignSalaryStructure::class)->handle($structure, SalaryStructureScope::Company, null, '2024-01-01', null, null);
    }

    $save = app(SaveStatutoryRuleVersion::class);
    $insurance = StatutoryRuleSet::create(['code' => 'T-SI', 'name_ar' => 'تأمينات', 'name_en' => 'SI', 'country_code' => 'EG', 'type' => 'social_insurance', 'is_demo' => true, 'is_active' => true]);
    $save->handle($insurance, ['effective_from' => '2025-01-01', 'employee_rate' => '10', 'employer_rate' => '15', 'salary_floor' => '2000', 'salary_ceiling' => '12000', 'base_reductions' => ['attendance', 'leave']], null);
    $tax = StatutoryRuleSet::create(['code' => 'T-TAX', 'name_ar' => 'ضريبة', 'name_en' => 'Tax', 'country_code' => 'EG', 'type' => 'income_tax', 'is_demo' => false, 'is_active' => true]);
    $save->handle($tax, ['effective_from' => '2025-01-01', 'annual_exemption' => '0', 'base_reductions' => ['attendance', 'leave'], 'deduct_employee_insurance' => true, 'brackets' => [
        ['lower_bound' => '0', 'upper_bound' => '30000', 'rate' => '0'],
        ['lower_bound' => '30000', 'upper_bound' => '60000', 'rate' => '10'],
        ['lower_bound' => '60000', 'upper_bound' => null, 'rate' => '20'],
    ]], null);

    return $c;
}

/**
 * The monthly payroll period of a month (created through the real action).
 */
function payrollPeriod(string $month = '2026-10'): PayrollPeriod
{
    return app(ManagePayrollPeriod::class)->monthly($month);
}

/**
 * A processed attendance day written directly (the attendance engine is covered by its own tests).
 *
 * @param  array<string, mixed>  $attributes
 */
function payrollAttendanceDay(Employee $employee, string $date, string $status = 'present', array $attributes = []): AttendanceDay
{
    return AttendanceDay::query()->create([
        'employee_id' => $employee->id, 'work_date' => $date, 'shift_key' => 0, 'branch_id' => $employee->branch_id, 'department_id' => $employee->department_id,
        'timezone' => 'Africa/Cairo', 'status' => $status, 'scheduled_minutes' => 480, 'worked_minutes' => $status === 'absent' ? 0 : 480, ...$attributes,
    ]);
}

/**
 * Create a run through the real action and calculate it.
 *
 * @param  array<string, mixed>  $scope
 */
function calculatedRun(PayrollPeriod $period, array $scope = ['all' => true], ?User $by = null, PayrollRunType $type = PayrollRunType::Regular): PayrollRun
{
    $by ??= adminWithRoles('Payroll Accountant');
    $runs = app(ManagePayrollRun::class);

    return $runs->calculate($runs->create($period, $type, $scope, $by), $by);
}

/**
 * Submit, approve at both levels (HR Manager, then Finance Officer) and finalise.
 */
function finalisedRun(PayrollRun $run, ?User $submitter = null): PayrollRun
{
    $runs = app(ManagePayrollRun::class);
    $finance = adminWithRoles('Finance Officer');
    $runs->submit($run, $submitter ?? adminWithRoles('Payroll Accountant'));
    $runs->decide($run->refresh(), adminWithRoles('HR Manager'), true);
    $runs->decide($run->refresh(), $finance, true);

    return $runs->finalise($run->refresh(), $finance);
}

/**
 * The calculated line of a component in an employee's item of a run (null when absent).
 */
function payLine(PayrollRun $run, Employee $employee, string $code): ?PayrollItemLine
{
    return PayrollItemLine::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->where('code', $code)->first();
}

function payItem(PayrollRun $run, Employee $employee): PayrollItem
{
    return PayrollItem::query()->withoutGlobalScopes()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->firstOrFail();
}
