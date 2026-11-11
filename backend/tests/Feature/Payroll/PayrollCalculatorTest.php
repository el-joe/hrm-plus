<?php

use App\Actions\Leave\AdjustLeaveBalance;
use App\Actions\Payroll\ManageLoan;
use App\Actions\Payroll\SaveStatutoryRuleVersion;
use App\Enums\PayBasis;
use App\Enums\PayComponentKind;
use App\Enums\PayrollLineSource;
use App\Enums\PayrollRunType;
use App\Enums\SalaryChangeType;
use App\Models\AttendanceAdjustment;
use App\Models\Holiday;
use App\Models\LeaveType;
use App\Models\SalaryComponent;
use App\Models\StatutoryRuleSet;
use App\Services\Employees\EmploymentHistory;
use App\Services\Payroll\Data\PayrollLine;
use App\Services\Payroll\Data\PayrollResult;
use App\Services\Payroll\PayrollCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\LeaveDefaultsSeeder;

/*
| The payroll engine (docs/PAYROLL_ENGINE.md §6): every rule type, proration
| for joiners / leavers / mid-period salary changes, pay bases, attendance
| quantities (locked-day adjustments, approved overtime with the policy
| multipliers), unpaid leave and encashment from the leave feed, statutory
| floor / ceiling / brackets with the configurable base, loans and net pay.
| A monthly salary of 6000 has a day rate of 200 and an hour rate of 25.
*/

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));
    seedAttendance();
    $this->c = payrollSetup();
    $this->period = payrollPeriod('2026-10');
    $this->calculate = fn ($employee, $type = PayrollRunType::Regular, ?string $leaving = null): PayrollResult => app(PayrollCalculator::class)->calculate($employee, $this->period, $type, null, $leaving);
    $this->line = fn (PayrollResult $result, string $code): ?PayrollLine => collect($result->lines)->firstWhere('code', $code);
});

describe('rule types', function () {
    it('calculates basic, percentage and fixed lines for a full month with an explanation', function () {
        $employee = paidEmployee('6000.00');
        $result = ($this->calculate)($employee);

        expect(($this->line)($result, 'BASIC')->amount)->toBe('6000.00')
            ->and(($this->line)($result, 'BASIC')->source)->toBe(PayrollLineSource::Basic)
            ->and(($this->line)($result, 'HOUSING')->amount)->toBe('1500.00')
            ->and(($this->line)($result, 'TRANSPORT')->amount)->toBe('500.00')
            ->and($result->grossPay)->toBe('8000.00')
            ->and($result->prorationFactor)->toBe('1.000000')
            ->and(($this->line)($result, 'HOUSING')->explanation[0]['key'])->toBe('percentage');
    });

    it('deducts late minutes at a fixed amount per minute and explains it', function () {
        $employee = paidEmployee('6000.00');
        payrollAttendanceDay($employee, '2026-10-05', 'late', ['late_minutes' => 12]);

        $line = ($this->line)(($this->calculate)($employee), 'LATE');

        expect($line->amount)->toBe('30.00')
            ->and($line->kind)->toBe(PayComponentKind::Deduction)
            ->and($line->explanation[0])->toMatchArray(['key' => 'late_minutes', 'params' => ['quantity' => '12', 'rate' => '2.50', 'amount' => '30.00']]);
    });

    it('deducts early-departure minutes and absent days at the salary rates', function () {
        $employee = paidEmployee('6000.00');
        payrollAttendanceDay($employee, '2026-10-05', 'present', ['early_leave_minutes' => 60]);
        payrollAttendanceDay($employee, '2026-10-06', 'absent');

        $result = ($this->calculate)($employee);

        expect(($this->line)($result, 'EARLY')->amount)->toBe('25.00')   // 60 × 0.416667
            ->and(($this->line)($result, 'ABSENCE')->amount)->toBe('200.00');
    });

    it('pays approved overtime only, with the multiplier of the day kind from the policy version', function () {
        $employee = paidEmployee('6000.00');
        $version = attendancePolicy(['overtime_rate_regular' => '1.50', 'overtime_rate_rest_day' => '1.75', 'overtime_rate_holiday' => '2.00'])->versions()->firstOrFail();
        $holiday = Holiday::query()->create(['name_ar' => 'عطلة', 'name_en' => 'Holiday', 'start_date' => '2026-10-06', 'end_date' => '2026-10-06']);

        payrollAttendanceDay($employee, '2026-10-05', 'present', ['overtime_minutes' => 120, 'overtime_approved_minutes' => 120, 'overtime_status' => 'approved', 'attendance_policy_version_id' => $version->id]);
        payrollAttendanceDay($employee, '2026-10-06', 'holiday', ['holiday_id' => $holiday->id, 'overtime_minutes' => 60, 'overtime_approved_minutes' => 60, 'overtime_status' => 'approved', 'attendance_policy_version_id' => $version->id]);
        payrollAttendanceDay($employee, '2026-10-07', 'present', ['overtime_minutes' => 90, 'overtime_status' => 'pending', 'attendance_policy_version_id' => $version->id]);

        $result = ($this->calculate)($employee);

        expect(($this->line)($result, 'OVERTIME')->amount)->toBe('125.00')   // 2 h × 25 × 1.5 + 1 h × 25 × 2
            ->and(collect($result->warnings)->pluck('code'))->toContain('overtime_pending');
    });

    it('reads locked days with their adjustments and drops removed days', function () {
        $employee = paidEmployee('6000.00');
        $day = payrollAttendanceDay($employee, '2026-10-05', 'late', ['late_minutes' => 10]);
        AttendanceAdjustment::create([
            'attendance_day_id' => $day->id, 'employee_id' => $employee->id, 'work_date' => '2026-10-05', 'status_before' => 'late', 'status_after' => 'late',
            'late_minutes_delta' => 6, 'reason' => 'test', 'calculation_hash' => str_repeat('a', 64),
        ]);
        $removed = payrollAttendanceDay($employee, '2026-10-06', 'absent');
        AttendanceAdjustment::create([
            'attendance_day_id' => $removed->id, 'employee_id' => $employee->id, 'work_date' => '2026-10-06', 'status_before' => 'absent', 'status_after' => 'removed',
            'reason' => 'test', 'calculation_hash' => str_repeat('b', 64),
        ]);

        $result = ($this->calculate)($employee);

        expect(($this->line)($result, 'LATE')->amount)->toBe('40.00')   // (10 + 6) × 2.50
            ->and(($this->line)($result, 'ABSENCE'))->toBeNull();
    });

    it('adds employee-level entries to the structure lines', function () {
        $employee = paidEmployee('6000.00');
        payEntry(PayComponentKind::Earning, $this->c['TRANSPORT'], ['target_type' => 'employee', 'target_id' => $employee->id, 'amount' => '200', 'recurrence' => 'one_time', 'start_date' => '2026-10-10']);

        $transport = collect(($this->calculate)($employee)->lines)->where('code', 'TRANSPORT');

        expect($transport)->toHaveCount(2)
            ->and($transport->sum(fn (PayrollLine $line) => (float) $line->amount))->toBe(700.0)
            ->and($transport->firstWhere('source', PayrollLineSource::Entry)->sourceType)->toBe('pay_entry');
    });
});

describe('proration', function () {
    it('prorates a joiner by calendar days', function () {
        $employee = paidEmployee('6000.00', attributes: ['hire_date' => '2026-10-11', 'original_hire_date' => '2026-10-11'], from: '2026-10-11');
        $result = ($this->calculate)($employee);

        expect($result->prorationFactor)->toBe('0.677419')   // 21 / 31
            ->and(($this->line)($result, 'BASIC')->amount)->toBe('4064.51')
            ->and(($this->line)($result, 'HOUSING')->amount)->toBe('1016.13')
            ->and(($this->line)($result, 'TRANSPORT')->amount)->toBe('338.71')
            ->and(collect($result->warnings)->pluck('code'))->toContain('partial_period');
    });

    it('prorates a leaver to the termination date', function () {
        $employee = paidEmployee('6000.00');
        $employee->forceFill(['termination_date' => '2026-10-20'])->saveQuietly();

        expect(($this->line)(($this->calculate)($employee->refresh()), 'BASIC')->amount)->toBe('3870.97');   // 20 / 31
    });

    it('prorates to the last working day of a final settlement prepared before termination', function () {
        $employee = paidEmployee('6000.00');

        expect(($this->line)(($this->calculate)($employee, PayrollRunType::FinalSettlement, '2026-10-20'), 'BASIC')->amount)->toBe('3870.97');
    });

    it('splits a mid-period salary change into segments and sums them', function () {
        $employee = paidEmployee('6000.00');
        app(EmploymentHistory::class)->recordSalary($employee, '9000.00', SalaryChangeType::Increase, CarbonImmutable::parse('2026-10-16'), applyNow: true);

        $result = ($this->calculate)($employee->refresh());

        expect(($this->line)($result, 'BASIC')->amount)->toBe('7548.39')   // 6000 × 15/31 + 9000 × 16/31
            ->and($result->inputs['contexts'])->toHaveCount(2)
            ->and(($this->line)($result, 'BASIC')->explanation)->toHaveCount(2);
    });

    it('pays a fixed basis in full without attendance deductions', function () {
        $employee = paidEmployee('6000.00', PayBasis::Fixed, ['hire_date' => '2026-10-11', 'original_hire_date' => '2026-10-11'], '2026-10-11');
        payrollAttendanceDay($employee, '2026-10-12', 'late', ['late_minutes' => 30]);

        $result = ($this->calculate)($employee);

        expect(($this->line)($result, 'BASIC')->amount)->toBe('6000.00')
            ->and(($this->line)($result, 'LATE'))->toBeNull()
            ->and(collect($result->warnings)->pluck('code'))->toContain('fixed_basis');
    });

    it('pays a daily basis for attended, holiday and paid-leave days only', function () {
        $employee = paidEmployee('300.00', PayBasis::Daily);
        payrollAttendanceDay($employee, '2026-10-04');
        payrollAttendanceDay($employee, '2026-10-05');
        payrollAttendanceDay($employee, '2026-10-06', 'late', ['late_minutes' => 4]);
        payrollAttendanceDay($employee, '2026-10-07', 'holiday');
        payrollAttendanceDay($employee, '2026-10-08', 'absent');

        $result = ($this->calculate)($employee);

        expect(($this->line)($result, 'BASIC')->amount)->toBe('1200.00')   // 4 paid days × 300
            ->and(($this->line)($result, 'ABSENCE'))->toBeNull()
            ->and(($this->line)($result, 'LATE')->amount)->toBe('10.00');
    });

    it('pays an hourly basis for the hours worked', function () {
        $employee = paidEmployee('40.00', PayBasis::Hourly);
        payrollAttendanceDay($employee, '2026-10-04', 'present', ['worked_minutes' => 450]);
        payrollAttendanceDay($employee, '2026-10-05', 'present', ['worked_minutes' => 390]);

        expect(($this->line)(($this->calculate)($employee), 'BASIC')->amount)->toBe('560.00');   // 14 h × 40
    });

    it('warns and pays nothing as basic when no salary is recorded', function () {
        $employee = attendanceEmployee(['hire_date' => '2024-01-01', 'original_hire_date' => '2024-01-01']);
        $result = ($this->calculate)($employee);

        expect(collect($result->warnings)->pluck('code'))->toContain('missing_salary')
            ->and(($this->line)($result, 'BASIC'))->toBeNull();
    });
});

describe('leave', function () {
    beforeEach(function () {
        $this->seed(LeaveDefaultsSeeder::class);
        leaveWorkWeek();
    });

    it('deducts unpaid leave days from the leave feed at the day rate', function () {
        $employee = paidEmployee('6000.00');
        $unpaid = LeaveType::factory()->unpaid()->create();
        $this->travelTo(CarbonImmutable::parse('2026-10-01 08:00:00'));
        fileLeave($employee, $unpaid, '2026-10-11', '2026-10-12', by: adminWithRoles('HR Manager'), recordAsApproved: true);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));

        $line = ($this->line)(($this->calculate)($employee), 'UNPAID');

        expect($line->amount)->toBe('400.00')
            ->and($line->source)->toBe(PayrollLineSource::Leave);
    });

    it('pays leave encashment booked in the period as days × day rate', function () {
        $employee = paidEmployee('6000.00');
        $annual = leaveTypeWithPolicy([], ['encashable' => true, 'encashment_max_days' => '10']);
        accrueLeave('2026-01-01', $employee->id);
        app(AdjustLeaveBalance::class)->handle($employee, [
            'leave_type_id' => $annual->id, 'leave_year' => 2026, 'type' => 'encashment', 'amount' => '2', 'effective_date' => '2026-10-15', 'reason' => 'Test',
        ], adminWithRoles('HR Manager'));

        $line = ($this->line)(($this->calculate)($employee), 'LEAVE_ENCASH');

        expect($line->amount)->toBe('400.00')
            ->and($line->source)->toBe(PayrollLineSource::Encashment)
            ->and($line->sourceType)->toBe('leave_balance_transaction');
    });
});

describe('statutory', function () {
    it('clamps the insurable base to the ceiling and splits employee / employer shares', function () {
        $result = ($this->calculate)(paidEmployee('15000.00'));

        expect(($this->line)($result, 'SI_EMP')->amount)->toBe('1200.00')
            ->and(($this->line)($result, 'SI_ER')->amount)->toBe('1800.00')
            ->and(($this->line)($result, 'SI_ER')->kind)->toBe(PayComponentKind::EmployerContribution)
            ->and($result->employerContributions)->toBe('1800.00');
    });

    it('raises the insurable base to the floor', function () {
        $result = ($this->calculate)(paidEmployee('1500.00'));

        expect(($this->line)($result, 'SI_EMP')->amount)->toBe('200.00');
    });

    it('applies progressive brackets after the employee insurance is deducted from taxable income', function () {
        // Taxable 6000 + 1500 + 500 = 8000; insurance 10 % of 6000 = 600 → 7400 × 12 = 88800
        // → 30000 × 10 % + 28800 × 20 % = 8760 a year → 730 a month.
        $result = ($this->calculate)(paidEmployee('6000.00'));

        expect(($this->line)($result, 'TAX')->amount)->toBe('730.00')
            ->and($result->taxableBase)->toBe('7400.00');
    });

    it('takes the taxable base from the statutory version configuration', function () {
        $employee = paidEmployee('6000.00');
        payrollAttendanceDay($employee, '2026-10-06', 'absent');
        $set = StatutoryRuleSet::query()->where('code', 'T-TAX')->firstOrFail();

        // Version 1: attendance deductions reduce taxable income: 8000 − 200 − 580 = 7220.
        expect(($this->calculate)($employee)->taxableBase)->toBe('7220.00');

        // Version 2 from October: nothing reduces the base except the insurance switched off: 8000.
        app(SaveStatutoryRuleVersion::class)->handle($set, ['effective_from' => '2026-10-01', 'annual_exemption' => '0', 'base_reductions' => [], 'deduct_employee_insurance' => false, 'brackets' => [
            ['lower_bound' => '0', 'upper_bound' => null, 'rate' => '10'],
        ]], null);

        $result = ($this->calculate)($employee);

        expect($result->taxableBase)->toBe('8000.00')
            ->and(($this->line)($result, 'TAX')->amount)->toBe('800.00');
    });

    it('labels demo tables and warns when a structure line has no table in force', function () {
        $result = ($this->calculate)(paidEmployee('6000.00'));
        expect(collect($result->warnings)->where('code', 'demo_statutory')->pluck('params.set')->all())->toContain('T-SI');

        StatutoryRuleSet::query()->update(['is_active' => false]);
        $result = ($this->calculate)(paidEmployee('6000.00'));
        expect(collect($result->warnings)->where('code', 'statutory_missing'))->toHaveCount(2)
            ->and(($this->line)($result, 'TAX'))->toBeNull();
    });
});

describe('loans and net pay', function () {
    it('deducts the loan instalment due and recovers only what net pay allows', function () {
        $employee = paidEmployee('6000.00');
        $this->travelTo(CarbonImmutable::parse('2026-09-10 09:00:00'));
        $loan = requestLoan($employee, ['amount' => '6000', 'instalments' => 6, 'start_month' => '2026-10']);
        app(ManageLoan::class)->decide($loan, adminWithRoles('Finance Officer'), true);
        $this->travelTo(CarbonImmutable::parse('2026-11-12 09:00:00'));

        $result = ($this->calculate)($employee);
        expect(($this->line)($result, 'LOAN')->amount)->toBe('1000.00')
            ->and($result->loanRecoveries)->toHaveCount(1);

        // A 6200 penalty leaves 8000 − 6200 − 600 (insurance) − 730 (tax) = 470 for the loan:
        // it is recovered partially (the rest stays scheduled), never a negative net.
        $penalty = SalaryComponent::factory()->deduction()->variable()->create(['code' => 'PENALTY']);
        payEntry(PayComponentKind::Deduction, $penalty, ['target_type' => 'employee', 'target_id' => $employee->id, 'amount' => '6200', 'recurrence' => 'one_time', 'start_date' => '2026-10-05']);
        $result = ($this->calculate)($employee);

        expect(($this->line)($result, 'LOAN')->amount)->toBe('470.00')
            ->and($result->netPay)->toBe('0.00')
            ->and($result->loanRecoveries[0]['amount'])->toBe('470.00');
        expect(bccomp($result->netPay, '0', 2))->toBeGreaterThanOrEqual(0)
            ->and(bccomp(($this->line)($result, 'LOAN')->amount, '1000.00', 2))->toBeLessThan(0)
            ->and(collect($result->warnings)->pluck('code'))->toContain('loan_partial');
    });

    it('marks a negative net pay as a blocking warning', function () {
        $employee = paidEmployee('6000.00');
        $penalty = SalaryComponent::factory()->deduction()->variable()->create(['code' => 'PENALTY']);
        payEntry(PayComponentKind::Deduction, $penalty, ['target_type' => 'employee', 'target_id' => $employee->id, 'amount' => '20000', 'recurrence' => 'one_time', 'start_date' => '2026-10-05']);

        $result = ($this->calculate)($employee);

        expect(bccomp($result->netPay, '0', 2))->toBeLessThan(0)
            ->and($result->hasBlockingWarning())->toBeTrue();
    });

    it('is deterministic: the same inputs give the same hash, a change gives another', function () {
        $employee = paidEmployee('6000.00');
        $first = ($this->calculate)($employee)->hash;

        expect(($this->calculate)($employee)->hash)->toBe($first);

        payrollAttendanceDay($employee, '2026-10-06', 'absent');
        expect(($this->calculate)($employee)->hash)->not->toBe($first);
    });
});
