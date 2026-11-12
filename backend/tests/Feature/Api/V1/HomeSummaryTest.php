<?php

use App\Enums\LeaveRequestSource;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AttendanceCorrection;
use App\Models\EmployeeLoan;
use App\Models\Holiday;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Attendance\HolidayCalendar;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

/*
| GET /api/v1/me/home (Phase 10): every home card in one answer, computed from the employee's own records
| — the same App\Services\Employees\PortalHome figures as the portal home page.
*/

beforeEach(function () {
    // Tuesday 10 November 2026, 07:00 UTC = 09:00 in Cairo.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 07:00:00'));
    seedLeave();
    leaveWorkWeek();
    $this->employee = attendanceEmployee();
    $this->user = User::factory()->employee()->create(['employee_id' => $this->employee->id]);
    $this->other = attendanceEmployee();
});

it('summarises today, this month, balances, pending items, upcoming days and tasks', function () {
    // This month: 2 Nov on time, 3 Nov late (09:00), 4 Nov absent; today checked in.
    punchAt($this->employee, '2026-11-02 08:00');
    punchAt($this->employee, '2026-11-02 16:00', 'out');
    punchAt($this->employee, '2026-11-03 09:00');
    punchAt($this->employee, '2026-11-03 16:00', 'out');
    foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $day) {
        processDay($this->employee, $day);
    }
    punchAt($this->employee, '2026-11-10 08:55');
    // The other employee's records must not count.
    punchAt($this->other, '2026-11-02 08:00');
    processDay($this->other, '2026-11-02');

    $annual = leaveTypeWithPolicy(['code' => 'ANNUAL_T'], ['entitlement_days' => '21.00']);
    accrueLeave('2026-01-01', $this->employee->id);
    fileLeave($this->employee, $annual, '2026-12-06', '2026-12-07', by: $this->user, source: LeaveRequestSource::Portal);
    AttendanceCorrection::query()->forceCreate(['employee_id' => $this->employee->id, 'work_date' => '2026-11-04', 'reason' => 'Was on site', 'source' => 'portal']);
    AttendanceCorrection::query()->forceCreate(['employee_id' => $this->other->id, 'work_date' => '2026-11-04', 'reason' => 'Theirs', 'source' => 'portal']);
    EmployeeLoan::query()->forceCreate(['employee_id' => $this->employee->id, 'reference' => 'LN-T-1', 'type' => 'loan', 'requested_amount' => '1000.00', 'requested_instalments' => 2, 'requested_start' => '2026-12-01', 'source' => 'portal']);
    AssetAssignment::query()->forceCreate(['asset_id' => Asset::factory()->create(['name' => 'Laptop T14'])->id, 'employee_id' => $this->employee->id, 'assigned_on' => '2026-11-01', 'condition_on_issue' => 'good', 'status' => 'active']);
    Holiday::create(['name_ar' => 'عيد', 'name_en' => 'Feast', 'start_date' => '2026-11-26', 'end_date' => '2026-11-27', 'is_recurring' => false, 'is_paid' => true, 'is_active' => true]);
    app(HolidayCalendar::class)->forget();

    Sanctum::actingAs($this->user, ['employee']);

    $data = $this->getJson(route('api.v1.me.home'), ['Accept-Language' => 'en'])->assertOk()->assertJsonPath('success', true)->json('data');

    expect($data['employee']['id'])->toBe($this->employee->id)
        ->and($data['attendance_today']['date'])->toBe('2026-11-10')
        ->and($data['attendance_today']['state'])->toBe('checked_in')
        ->and($data['attendance_today']['next_action'])->toBe('check_out')
        ->and($data['attendance_today']['check_in_local'])->toBe('08:55')
        ->and($data['attendance_today']['shifts'][0]['start_local'])->toBe('08:00')
        ->and($data['attendance_month']['month'])->toBe('2026-11')
        ->and($data['attendance_month']['present_days'])->toBe(1)
        ->and($data['attendance_month']['late_days'])->toBe(1)
        ->and($data['attendance_month']['absent_days'])->toBe(1)
        ->and($data['pending'])->toBe([
            'leave_requests' => 1, 'hr_requests' => 0, 'loan_requests' => 1, 'attendance_corrections' => 1, 'announcements_to_acknowledge' => 0,
        ])
        ->and(collect($data['leave_balances'])->firstWhere('leave_type.code', 'ANNUAL_T')['pending'])->toBe('2.00')
        ->and($data['latest_payslip'])->toBeNull()
        ->and($data['upcoming']['holidays'])->toBe([['date' => '2026-11-26', 'name' => 'Feast']])
        ->and($data['upcoming']['shifts'][0])->toMatchArray(['date' => '2026-11-11', 'start_local' => '08:00', 'end_local' => '16:00'])
        ->and($data['tasks'])->toBe([['kind' => 'acknowledge_asset', 'id' => AssetAssignment::query()->value('id'), 'title' => 'Laptop T14', 'due_date' => null]])
        ->and($data['unread_notifications'])->toBe(0);
});

it('includes the latest issued payslip of the employee only', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00:00'));
    payrollSetup();
    $mine = paidEmployee('6000.00');
    paidEmployee('9000.00');
    $account = User::factory()->employee()->create(['employee_id' => $mine->id]);
    finalisedRun(calculatedRun(payrollPeriod('2026-10')));
    $payslip = Payslip::query()->where('employee_id', $mine->id)->sole();

    Sanctum::actingAs($account, ['employee']);

    $this->getJson(route('api.v1.me.home'))->assertOk()
        ->assertJsonPath('data.latest_payslip.id', $payslip->id)
        ->assertJsonPath('data.latest_payslip.net_pay', (string) $payslip->net_pay)
        ->assertJsonPath('data.latest_payslip.pdf_url', route('api.v1.me.payslips.pdf', $payslip->id));
});

it('answers 404 for an account without an employee profile', function () {
    Sanctum::actingAs(User::factory()->employee()->create(['employee_id' => null]), ['employee']);

    $this->getJson(route('api.v1.me.home'))->assertNotFound()->assertJsonPath('code', 'not_found');
});
