<?php

use App\Enums\ApplicationStatus;
use App\Enums\EmployeeStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\RawEventSource;
use App\Models\AttendanceRawEvent;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\JobApplication;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\ReportRunner;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;

/*
| Phase 8c — query correctness of the reports centre on a small dataset, one or more reports per
| area. Rows are read through ReportRunner::stream() (the same path as every export). Written, NOT run.
*/

beforeEach(function () {
    seedRoles();
    app(SettingsService::class)->set('localisation.default_locale', 'en');
    app(SettingsService::class)->set('localisation.timezone', 'Africa/Cairo');
    $this->travelTo(CarbonImmutable::parse('2026-11-10 09:00:00'));
    $this->admin = superAdmin();
    $this->cairo = Branch::factory()->create(['code' => 'CAI']);
    $this->sales = Department::factory()->create(['branch_id' => $this->cairo->id, 'code' => 'SAL']);
});

/**
 * @param  array<string, mixed>  $filters
 * @return list<array<string, mixed>>
 */
function reportRows(User $admin, string $key, array $filters = []): array
{
    $report = app(ReportRegistry::class)->find($key);

    return iterator_to_array(app(ReportRunner::class)->stream($report, app(ReportFilters::class)->resolve($report, $filters), $admin), false);
}

describe('employees', function () {
    it('lists new hires and terminations of the period only', function () {
        Employee::factory()->create(['employee_number' => 'NEW', 'branch_id' => $this->cairo->id, 'department_id' => $this->sales->id, 'hire_date' => '2026-11-03']);
        Employee::factory()->create(['employee_number' => 'OLD', 'branch_id' => $this->cairo->id, 'department_id' => $this->sales->id, 'hire_date' => '2020-01-01']);
        Employee::factory()->create(['employee_number' => 'GONE', 'branch_id' => $this->cairo->id, 'department_id' => $this->sales->id, 'hire_date' => '2020-01-01',
            'status' => EmployeeStatus::Terminated, 'termination_date' => '2026-11-05', 'termination_type' => 'resignation']);

        expect(array_column(reportRows($this->admin, 'new_hires', ['from' => '2026-11-01', 'to' => '2026-11-10']), 'employee_number'))->toBe(['NEW'])
            ->and(array_column(reportRows($this->admin, 'terminated_employees', ['from' => '2026-11-01', 'to' => '2026-11-10']), 'employee_number'))->toBe(['GONE'])
            ->and(reportRows($this->admin, 'terminated_employees', ['from' => '2026-11-01', 'to' => '2026-11-10'])[0]['service_years'])->toBe('6.8');
    });

    it('computes monthly turnover from hires and terminations', function () {
        Employee::factory()->count(3)->create(['branch_id' => $this->cairo->id, 'department_id' => $this->sales->id, 'hire_date' => '2020-01-01']);
        Employee::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->sales->id, 'hire_date' => '2026-10-10']);
        Employee::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->sales->id, 'hire_date' => '2020-01-01',
            'status' => EmployeeStatus::Terminated, 'termination_date' => '2026-10-20']);

        $october = collect(reportRows($this->admin, 'employee_turnover', ['from' => '2026-10-01', 'to' => '2026-10-31']))->sole();

        expect($october)->toMatchArray(['month' => '2026-10', 'opening' => 4, 'hires' => 1, 'terminations' => 1, 'closing' => 4])
            ->and($october['turnover_rate'])->toBe(25.0);
    });

    it('groups the distribution and the head count by branch', function () {
        $alex = Branch::factory()->create(['code' => 'ALX']);
        Employee::factory()->count(2)->create(['branch_id' => $this->cairo->id, 'department_id' => $this->sales->id]);
        Employee::factory()->inactive()->create(['branch_id' => $alex->id, 'department_id' => Department::factory()->create(['branch_id' => $alex->id])->id]);

        $byBranch = collect(reportRows($this->admin, 'employees_by_branch'))->keyBy('code');
        $distribution = collect(reportRows($this->admin, 'employee_distribution', ['group_by' => 'branch']));

        expect($byBranch['CAI'])->toMatchArray(['active' => 2, 'inactive' => 0, 'employed' => 2])
            ->and($byBranch['ALX'])->toMatchArray(['active' => 0, 'inactive' => 1, 'employed' => 1])
            ->and($distribution->sum('total'))->toBe(3)
            ->and($distribution->firstWhere('total', 2)['share'])->toBe(66.7);
    });

    it('lists contracts ending in the period, excluding renewed and terminated ones', function () {
        $employee = Employee::factory()->create(['employee_number' => 'C1', 'branch_id' => $this->cairo->id, 'department_id' => $this->sales->id]);
        EmployeeContract::query()->create(['employee_id' => $employee->id, 'contract_type' => 'fixed_term', 'start_date' => '2025-12-01', 'end_date' => '2026-12-01']);
        EmployeeContract::query()->create(['employee_id' => $employee->id, 'contract_type' => 'fixed_term', 'start_date' => '2025-12-01', 'end_date' => '2026-12-05', 'terminated_on' => '2026-06-01']);

        $rows = reportRows($this->admin, 'contract_expiration');

        expect($rows)->toHaveCount(1)->and($rows[0]['days_left'])->toBe(21);
    });
});

describe('attendance', function () {
    it('reports early departures and missing punches from processed days', function () {
        $employee = attendanceEmployee(['employee_number' => 'A1', 'branch_id' => $this->cairo->id, 'department_id' => $this->sales->id]);
        payrollAttendanceDay($employee, '2026-11-02', 'present', ['early_leave_minutes' => 25]);
        payrollAttendanceDay($employee, '2026-11-03', 'incomplete', ['flags' => ['missing_check_out'], 'first_in_at' => '2026-11-03 06:00:00']);
        payrollAttendanceDay($employee, '2026-11-04', 'present');

        $early = reportRows($this->admin, 'early_departures', ['from' => '2026-11-01', 'to' => '2026-11-10']);
        $missing = reportRows($this->admin, 'missing_punches', ['from' => '2026-11-01', 'to' => '2026-11-10']);

        expect($early)->toHaveCount(1)->and($early[0]['early_minutes'])->toBe(25)
            ->and($missing)->toHaveCount(1)->and($missing[0]['missing'])->toBe(__('attendance.flags.missing_check_out'));
    });

    it('counts attendance events by source', function () {
        $employee = attendanceEmployee(['branch_id' => $this->cairo->id, 'department_id' => $this->sales->id]);
        foreach ([RawEventSource::Device, RawEventSource::Device, RawEventSource::Manual] as $index => $source) {
            AttendanceRawEvent::query()->create([
                'employee_id' => $employee->id, 'kind' => 'punch', 'direction' => 'in', 'source' => $source, 'status' => 'processed',
                'occurred_at' => '2026-11-0'.($index + 2).' 06:00:00', 'work_date' => '2026-11-0'.($index + 2), 'timezone' => 'Africa/Cairo',
                'idempotency_key' => 'test-'.$index,
            ]);
        }

        $rows = collect(reportRows($this->admin, 'attendance_source', ['from' => '2026-11-01', 'to' => '2026-11-10']))->keyBy('source_key');

        expect($rows['device']['events'])->toBe(2)->and($rows['manual']['events'])->toBe(1)->and($rows['device']['share'])->toBe(66.7);
    });

    it('reuses the Phase 5b daily report with the extra employee filters', function () {
        $first = attendanceEmployee(['employee_number' => 'D1', 'branch_id' => $this->cairo->id, 'department_id' => $this->sales->id]);
        $second = attendanceEmployee(['employee_number' => 'D2', 'branch_id' => $this->cairo->id, 'department_id' => $this->sales->id, 'status' => EmployeeStatus::Inactive]);
        payrollAttendanceDay($first, '2026-11-02');
        payrollAttendanceDay($second, '2026-11-02');

        expect(array_column(reportRows($this->admin, 'daily_attendance', ['from' => '2026-11-01', 'to' => '2026-11-10', 'employee_status' => 'inactive']), 'employee_number'))->toBe(['D2']);
    });
});

describe('leave', function () {
    it('separates approved and rejected leave through the reused Phase 6 report', function () {
        seedLeave();
        leaveWorkWeek();
        $type = leaveTypeWithPolicy(['code' => 'ANNUAL', 'name_en' => 'Annual']);
        $employee = attendanceEmployee(['branch_id' => $this->cairo->id, 'department_id' => $this->sales->id, 'hire_date' => '2024-01-01']);
        accrueLeave('2026-01-01');
        $approved = fileLeave($employee, $type, '2026-11-15', '2026-11-16', recordAsApproved: true, by: adminWithRoles('HR Manager'));
        $rejected = fileLeave($employee, $type, '2026-11-20');
        $rejected->forceFill(['status' => LeaveRequestStatus::Rejected, 'rejection_reason' => 'Busy'])->save();

        expect(array_column(reportRows($this->admin, 'approved_leaves'), 'id'))->toBe([$approved->id])
            ->and(array_column(reportRows($this->admin, 'rejected_leaves'), 'id'))->toBe([$rejected->id]);
    });
});

describe('payroll', function () {
    it('totals live pay of finalised runs by period and by department', function () {
        payrollSetup();
        $first = paidEmployee('6000.00');
        $second = paidEmployee('4000.00');
        $run = finalisedRun(calculatedRun(payrollPeriod('2026-10')));
        calculatedRun(payrollPeriod('2026-11'));
        $net = bcadd((string) payItem($run, $first)->net_pay, (string) payItem($run, $second)->net_pay, 2);

        $summary = reportRows($this->admin, 'payroll_summary', ['from' => '2026-01-01', 'to' => '2026-12-31']);
        $byDepartment = reportRows($this->admin, 'payroll_by_department', ['from' => '2026-01-01', 'to' => '2026-12-31']);

        expect($summary)->toHaveCount(1)
            ->and(bccomp($summary[0]['net'], $net, 2))->toBe(0)
            ->and($summary[0]['employees'])->toBe(2)
            ->and(collect($byDepartment)->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['net'], 2), '0'))->toBe($net);
    });

    it('lists the overtime and deduction lines of live pay', function () {
        payrollSetup();
        $employee = paidEmployee('6000.00');
        payrollAttendanceDay($employee, '2026-10-05', 'late', ['late_minutes' => 12]);
        $run = finalisedRun(calculatedRun(payrollPeriod('2026-10')));
        $late = payLine($run, $employee, 'LATE');

        $rows = collect(reportRows($this->admin, 'deductions', ['from' => '2026-10-01', 'to' => '2026-10-31', 'component' => 'LATE']));

        expect($rows)->toHaveCount(1)->and(bccomp($rows->first()['amount'], (string) $late->amount, 2))->toBe(0);
    });

    it('reports loans with recovered and outstanding amounts', function () {
        seedPayroll();
        $employee = paidEmployee('8000.00');
        $loan = requestLoan($employee, ['amount' => '1200.00', 'instalments' => 3]);
        $loan->forceFill(['status' => 'active', 'approved_amount' => '1200.00', 'outstanding_amount' => '800.00', 'start_date' => '2026-10-01'])->save();

        $row = collect(reportRows($this->admin, 'loan_balances', ['from' => '2026-01-01', 'to' => '2026-12-31']))->sole();

        expect($row)->toMatchArray(['approved' => '1200.00', 'recovered' => '400.00', 'outstanding' => '800.00']);
    });
});

describe('recruitment', function () {
    it('measures conversion, hiring sources and time to hire', function () {
        seedRecruitment();
        $vacancy = Vacancy::factory()->create(['branch_id' => $this->cairo->id, 'department_id' => $this->sales->id, 'publish_at' => '2026-10-01']);
        JobApplication::factory()->count(3)->create(['vacancy_id' => $vacancy->id, 'applied_at' => '2026-10-05 10:00:00', 'source' => 'linkedin']);
        JobApplication::factory()->create(['vacancy_id' => $vacancy->id, 'applied_at' => '2026-10-05 10:00:00', 'source' => 'referral',
            'status' => ApplicationStatus::Hired, 'hired_at' => '2026-10-25 10:00:00']);

        $conversion = collect(reportRows($this->admin, 'recruitment_conversion', ['from' => '2026-10-01', 'to' => '2026-10-31']))->sole();
        $sources = collect(reportRows($this->admin, 'hiring_sources', ['from' => '2026-10-01', 'to' => '2026-10-31']));
        $hire = collect(reportRows($this->admin, 'time_to_hire', ['from' => '2026-10-01', 'to' => '2026-10-31']))->sole();

        expect($conversion)->toMatchArray(['applications' => 4, 'hired' => 1, 'hire_rate' => 25.0])
            ->and($sources->sum('applications'))->toBe(4)
            ->and($sources->firstWhere('hired', 1)['hire_rate'])->toBe(100.0)
            ->and($hire)->toMatchArray(['days_to_hire' => 20, 'days_from_publish' => 24]);
    });
});
