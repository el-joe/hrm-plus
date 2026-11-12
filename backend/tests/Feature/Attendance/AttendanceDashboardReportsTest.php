<?php

use App\Enums\AttendanceScope;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Shift;
use App\Services\Attendance\Reports\AttendanceDashboard;
use Illuminate\Support\Carbon;

/*
| Attendance dashboard, calendar and reports (Phase 5b): figures from real
| data, data scope on screens and exports, permissions.
| 2026-11-01 is a Sunday (working day), Cairo is UTC+2; "now" is 10:00 Cairo.
*/

beforeEach(function () {
    seedRoles();
    seedAttendance();
    attendancePolicy(['late_grace_minutes' => 10]);

    $this->cairo = Branch::factory()->create(['code' => 'CAI', 'timezone' => 'Africa/Cairo']);
    $this->alex = Branch::factory()->create(['code' => 'ALX', 'timezone' => 'Africa/Cairo']);
    $sales = Department::factory()->create(['code' => 'SAL', 'branch_id' => $this->cairo->id]);
    $ops = Department::factory()->create(['code' => 'OPS', 'branch_id' => $this->alex->id]);
    assignSchedule(workWeek(Shift::factory()->between('08:00', '16:00')->create(['code' => 'MOR'])), AttendanceScope::Company, null);

    // Employee numbers that cannot occur elsewhere in a page (inline font CSS, the employee filter shows "number · name").
    $make = fn (string $number, Branch $branch, Department $department) => attendanceEmployee([
        'employee_number' => $number, 'branch_id' => $branch->id, 'department_id' => $department->id,
    ]);
    $this->onTime = $make('CAI-ONE', $this->cairo, $sales);
    $this->late = $make('CAI-TWO', $this->cairo, $sales);
    $this->missing = $make('CAI-THREE', $this->cairo, $sales);
    $this->alexOnTime = $make('ALX-ONE', $this->alex, $ops);

    // Yesterday-style complete data for 2026-11-01 (processed), and a punch "today" for the live view.
    punchAt($this->onTime, '2026-11-01 07:58', 'in');
    punchAt($this->onTime, '2026-11-01 16:02', 'out');
    punchAt($this->late, '2026-11-01 08:40', 'in');
    punchAt($this->late, '2026-11-01 17:30', 'out');
    punchAt($this->alexOnTime, '2026-11-01 08:00', 'in');
    punchAt($this->alexOnTime, '2026-11-01 16:00', 'out');
    foreach ([$this->onTime, $this->late, $this->missing, $this->alexOnTime] as $employee) {
        processDay($employee, '2026-11-01');
    }
});

afterEach(fn () => Carbon::setTestNow());

it('counts scheduled, present, late and absent employees from the processed days', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-02 10:00', 'Africa/Cairo'));

    $data = app(AttendanceDashboard::class)->build(superAdmin(), ['date' => '2026-11-01']);

    expect($data['expected'])->toBe(4)
        ->and($data['totals'])->toBe(['present' => 2, 'late' => 1, 'absent' => 1, 'on_leave' => 0, 'not_in' => 0])
        ->and($data['attendance_rate'])->toBe(75)
        ->and(collect($data['by_branch'])->firstWhere('code', 'CAI'))->toMatchArray(['expected' => 3, 'present' => 1, 'late' => 1, 'absent' => 1])
        ->and(collect($data['by_branch'])->firstWhere('code', 'ALX'))->toMatchArray(['expected' => 1, 'present' => 1]);
});

it('shows employees without attendance as "not yet in" today until their shift is over', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-01 10:00', 'Africa/Cairo'));

    $data = app(AttendanceDashboard::class)->build(superAdmin(), ['date' => '2026-11-01']);
    expect($data['is_today'])->toBeTrue()->and($data['totals']['not_in'])->toBe(1)->and($data['totals']['absent'])->toBe(0);

    Carbon::setTestNow(Carbon::parse('2026-11-01 17:00', 'Africa/Cairo'));
    $data = app(AttendanceDashboard::class)->build(superAdmin(), ['date' => '2026-11-01']);
    expect($data['totals']['not_in'])->toBe(0)->and($data['totals']['absent'])->toBe(1);
});

it('renders the dashboard inside the admin data scope', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-02 10:00', 'Africa/Cairo'));

    $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')->get(route('admin.attendance.dashboard', ['date' => '2026-11-01']))
        ->assertOk()->assertSee('data-count="present">2<', false)->assertSee('data-count="absent">1<', false);

    $scoped = adminWithRoles('Branch Manager');
    $scoped->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->alex->id]);
    $this->actingAs($scoped, 'admin')->get(route('admin.attendance.dashboard', ['date' => '2026-11-01']))
        ->assertOk()->assertSee('data-count="present">1<', false)->assertSee('data-count="late">0<', false)->assertDontSee('CAI-TWO');

    $this->actingAs(adminWithRoles('Recruiter'), 'admin')->get(route('admin.attendance.dashboard'))->assertForbidden();
});

it('lists report rows from the processed days and exports only what is in scope', function () {
    $admin = adminWithRoles('Attendance Officer');
    $filters = ['from' => '2026-11-01', 'to' => '2026-11-01'];

    $this->actingAs($admin, 'admin')->get(route('admin.attendance.reports.show', ['lateness', ...$filters]))
        ->assertOk()->assertSee('CAI-TWO')->assertDontSee('CAI-ONE');
    $this->actingAs($admin, 'admin')->get(route('admin.attendance.reports.show', ['absence', ...$filters]))
        ->assertOk()->assertSee('CAI-THREE')->assertDontSee('CAI-TWO');

    $summary = $this->actingAs($admin, 'admin')->get(route('admin.attendance.reports.show', ['monthly_summary', ...$filters]))->assertOk()->viewData('rows');
    $late = collect($summary->items())->firstWhere('employee_number', 'CAI-TWO');
    expect($summary->total())->toBe(4)
        ->and($late['late_days'])->toBe(1)
        ->and($late['scheduled_days'])->toBe(1);

    $scoped = adminWithRoles('Branch Manager');
    $scoped->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $this->alex->id]);
    $response = $this->actingAs($scoped, 'admin')->get(route('admin.attendance.reports.export', ['daily', ...$filters, 'format' => 'csv']))->assertOk();

    $csv = file_get_contents($response->baseResponse->getFile()->getPathname());
    expect($csv)->toContain('ALX-ONE')->not->toContain('CAI-ONE')->not->toContain('CAI-TWO');
});

it('opens attendance reports to report viewers, keeps device sync for device viewers and blocks others', function () {
    $viewer = adminWithRoles('Reports Viewer');
    $this->actingAs($viewer, 'admin')->get(route('admin.attendance.reports.index'))->assertOk()->assertDontSee(route('admin.attendance.reports.show', 'device_sync'));
    $this->actingAs($viewer, 'admin')->get(route('admin.attendance.reports.show', 'daily'))->assertOk();
    $this->actingAs($viewer, 'admin')->get(route('admin.attendance.reports.show', 'device_sync'))->assertNotFound();
    $this->actingAs($viewer, 'admin')->get(route('admin.attendance.reports.export', ['overtime', 'format' => 'xlsx']))->assertOk();

    $this->actingAs(adminWithRoles('Attendance Officer'), 'admin')->get(route('admin.attendance.reports.show', 'device_sync'))->assertOk();
    // reports.view alone opens them (the Recruiter role holds it), so "others" = neither attendance.view nor reports.view.
    $this->actingAs(adminWithPermissions('recruitment.view'), 'admin')->get(route('admin.attendance.reports.index'))->assertForbidden();
    $this->actingAs(adminWithRoles('HR Specialist'), 'admin')->get(route('admin.attendance.reports.export', ['daily']))->assertForbidden();

    $this->actingAs(superAdmin(), 'admin')->get(route('admin.attendance.reports.show', ['daily', 'from' => '2025-01-01', 'to' => '2026-11-01']))
        ->assertSessionHasErrors('to');
});

it('renders the monthly calendar for an employee and for a department', function () {
    $officer = adminWithRoles('Attendance Officer');

    $this->actingAs($officer, 'admin')->get(route('admin.attendance.calendar', ['month' => '2026-11', 'employee_id' => $this->late->id]))
        ->assertOk()->assertSee(route('admin.attendance.days.show', AttendanceDay::query()->where('employee_id', $this->late->id)->firstOrFail()), false);
    // The employee filter lists every employee ("number · name"); the matrix rows show the number alone.
    $this->actingAs($officer, 'admin')->get(route('admin.attendance.calendar', ['month' => '2026-11', 'branch_id' => $this->cairo->id]))
        ->assertOk()->assertSee('>CAI-ONE<', false)->assertSee('>CAI-THREE<', false)->assertDontSee('>ALX-ONE<', false);
});
