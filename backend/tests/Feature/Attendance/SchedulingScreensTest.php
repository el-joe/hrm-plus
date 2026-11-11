<?php

use App\Enums\AttendanceScope;
use App\Enums\RequestStatus;
use App\Models\AttendancePolicy;
use App\Models\AttendanceStatus;
use App\Models\Holiday;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleChangeRequest;
use App\Models\ScheduleOverride;
use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendancePolicyResolver;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;

beforeEach(function () {
    seedAttendance();
    $this->officer = adminWithRoles('Attendance Officer');
});

it('refuses every configuration screen to an admin without the permissions', function () {
    $admin = adminWithPermissions('dashboard.view');

    foreach (['shifts', 'work-schedules', 'schedule-assignments', 'schedule-calendar', 'schedule-changes', 'holidays', 'attendance-policies', 'attendance-statuses'] as $path) {
        $this->actingAs($admin, 'admin')->get("/admin/{$path}")->assertForbidden();
    }

    $this->actingAs($admin, 'admin')->post('/admin/shifts', [])->assertForbidden();
    $this->actingAs($admin, 'admin')->post('/admin/schedule-calendar/override', [])->assertForbidden();
});

it('lets the attendance officer create and edit a shift with breaks, and delete it while unused', function () {
    $this->actingAs($this->officer, 'admin')->get('/admin/shifts/create')->assertOk()->assertSee('breaks[0][name]', false);

    $this->actingAs($this->officer, 'admin')->post('/admin/shifts', [
        'code' => 'ngt', 'name_ar' => 'ليلية', 'name_en' => 'Night', 'type' => 'fixed', 'start_time' => '22:00', 'end_time' => '06:00',
        'checkin_opens_before_minutes' => 60, 'checkout_closes_after_minutes' => 180, 'color' => 'chart-4', 'is_night' => '1', 'is_active' => '1',
        'breaks' => [
            ['name' => 'Meal', 'type' => 'fixed', 'start_time' => '02:00', 'end_time' => '02:30', 'duration_minutes' => '', 'is_paid' => '0'],
            ['name' => '', 'type' => 'fixed', 'start_time' => '', 'end_time' => '', 'duration_minutes' => '', 'is_paid' => '0'],
            ['name' => 'Tea', 'type' => 'flexible', 'start_time' => '', 'end_time' => '', 'duration_minutes' => '15', 'is_paid' => '1'],
        ],
    ])->assertRedirect('/admin/shifts')->assertSessionHasNoErrors();

    $shift = Shift::query()->where('code', 'NGT')->sole();
    expect($shift->crossesMidnight())->toBeTrue()->and($shift->is_night)->toBeTrue()
        ->and($shift->breaks->pluck('duration_minutes')->all())->toBe([30, 15])
        ->and($shift->expectedMinutes())->toBe(450);

    $this->actingAs($this->officer, 'admin')->get('/admin/shifts')->assertOk()->assertSee('NGT')->assertSee(__('schedules.common.crosses_midnight'));

    $this->actingAs($this->officer, 'admin')->put("/admin/shifts/{$shift->id}", [
        'code' => 'NGT', 'name_ar' => 'ليلية', 'name_en' => 'Night', 'type' => 'flexible', 'start_time' => '20:00', 'end_time' => '08:00',
        'required_minutes' => 480, 'checkin_opens_before_minutes' => 60, 'checkout_closes_after_minutes' => 60, 'color' => 'chart-4', 'breaks' => [],
    ])->assertSessionHasNoErrors();
    expect($shift->fresh()->required_minutes)->toBe(480)->and($shift->breaks()->count())->toBe(0);

    $this->actingAs($this->officer, 'admin')->delete("/admin/shifts/{$shift->id}")->assertSessionHasNoErrors();
    expect(Shift::query()->whereKey($shift->id)->exists())->toBeFalse();
});

it('requires required minutes for a flexible shift', function () {
    $this->actingAs($this->officer, 'admin')->post('/admin/shifts', [
        'code' => 'FLX', 'name_ar' => 'مرنة', 'name_en' => 'Flex', 'type' => 'flexible', 'start_time' => '07:00', 'end_time' => '19:00',
        'checkin_opens_before_minutes' => 60, 'checkout_closes_after_minutes' => 60, 'color' => 'chart-1',
    ])->assertSessionHasErrors('required_minutes');
});

it('saves a work schedule pattern, ignoring days beyond the cycle, and refuses deleting a schedule in use', function () {
    $day = Shift::factory()->create();
    $evening = Shift::factory()->between('16:00', '23:00')->create();

    $this->actingAs($this->officer, 'admin')->post('/admin/work-schedules', [
        'code' => 'ROT', 'name_ar' => 'دوري', 'name_en' => 'Rotation', 'type' => 'rotating', 'cycle_length_days' => 3, 'cycle_start_date' => '2026-11-01',
        'is_active' => '1',
        'pattern' => [
            'rotating' => [0 => [$day->id], 1 => [$day->id, $evening->id], 2 => [], 5 => [$day->id]],
            'weekly' => [0 => [$evening->id]],
        ],
    ])->assertRedirect('/admin/work-schedules')->assertSessionHasNoErrors();

    $schedule = WorkSchedule::query()->where('code', 'ROT')->sole();
    expect($schedule->pattern()->map(fn ($ids) => count($ids))->all())->toBe([0 => 1, 1 => 2]);

    $this->actingAs($this->officer, 'admin')->get("/admin/work-schedules/{$schedule->id}/edit")->assertOk();

    assignSchedule($schedule, AttendanceScope::Company, null);
    $this->actingAs($this->officer, 'admin')->delete("/admin/work-schedules/{$schedule->id}")->assertSessionHasErrors('record');
});

it('prefills the assignment form from the onboarding link and stores an employee assignment', function () {
    $employee = attendanceEmployee();
    $schedule = workWeek(Shift::factory()->create());

    $this->actingAs($this->officer, 'admin')->get("/admin/schedule-assignments/create?scope_type=employee&scope_id={$employee->id}")
        ->assertOk()->assertSee('value="'.$employee->id.'" selected', false);

    $this->actingAs($this->officer, 'admin')->post('/admin/schedule-assignments', [
        'scope_type' => 'employee', 'scope_ids' => ['employee' => $employee->id, 'branch' => ''], 'work_schedule_id' => $schedule->id,
        'effective_from' => '2026-11-01',
    ])->assertRedirect('/admin/schedule-assignments')->assertSessionHasNoErrors();

    $assignment = ScheduleAssignment::query()->sole();
    expect($assignment->scope_type)->toBe(AttendanceScope::Employee)->and($assignment->scope_id)->toBe($employee->id)
        ->and($assignment->created_by)->toBe($this->officer->id);

    $this->actingAs($this->officer, 'admin')->get('/admin/schedule-assignments')->assertOk()->assertSee($employee->employee_number);

    $this->actingAs($this->officer, 'admin')->post('/admin/schedule-assignments', [
        'scope_type' => 'department', 'scope_ids' => [], 'work_schedule_id' => $schedule->id, 'effective_from' => '2026-11-01',
    ])->assertSessionHasErrors('scope_ids.department');
});

it('manages public holidays', function () {
    $this->actingAs($this->officer, 'admin')->post('/admin/holidays', [
        'name_ar' => 'عيد', 'name_en' => 'Feast', 'start_date' => '2026-12-24', 'end_date' => '2026-12-26', 'is_recurring' => '1', 'is_paid' => '1', 'is_active' => '1',
    ])->assertSessionHasNoErrors();

    $holiday = Holiday::query()->sole();
    expect($holiday->is_recurring)->toBeTrue()->and($holiday->branch_id)->toBeNull();

    $this->actingAs($this->officer, 'admin')->get('/admin/holidays?year=2027')->assertOk()->assertSee($holiday->name)->assertSee('2027-12-24');
    $this->actingAs($this->officer, 'admin')->delete("/admin/holidays/{$holiday->id}")->assertSessionHasNoErrors();
    expect(Holiday::query()->count())->toBe(0);
});

it('creates a policy with version 1, adds a later version only and assigns it', function () {
    $hr = adminWithRoles('HR Manager');
    $rules = [
        'late_grace_minutes' => 5, 'late_count_from_shift_start' => '1', 'late_rounding_minutes' => 0, 'early_leave_grace_minutes' => 5,
        'early_leave_rounding_minutes' => 0, 'missing_punch_action' => 'incomplete', 'punch_dedupe_seconds' => 60, 'overtime_enabled' => '1',
        'overtime_min_minutes' => 30, 'overtime_rounding_minutes' => 15, 'overtime_requires_approval' => '1', 'overtime_rate_regular' => '1.50',
        'overtime_rate_rest_day' => '2.00', 'overtime_rate_holiday' => '2.00', 'break_mode' => 'scheduled', 'manual_entry_requires_approval' => '0',
        'correction_requires_approval' => '1',
    ];

    $this->actingAs($this->officer, 'admin')->post('/admin/attendance-policies', [])->assertForbidden();
    $this->actingAs($hr, 'admin')->get('/admin/attendance-policies/create')->assertOk();
    $this->actingAs($hr, 'admin')->post('/admin/attendance-policies', [
        'code' => 'shop', 'name_ar' => 'المتاجر', 'name_en' => 'Shops', 'is_active' => '1', 'effective_from' => '2026-01-01', ...$rules,
    ])->assertSessionHasNoErrors();

    $policy = AttendancePolicy::query()->where('code', 'SHOP')->sole();
    expect($policy->versions()->sole()->late_grace_minutes)->toBe(5)
        ->and($policy->versions()->sole()->overtime_rate_rest_day)->toBe('2.00');

    $this->actingAs($hr, 'admin')->post("/admin/attendance-policies/{$policy->id}/versions", [...$rules, 'effective_from' => '2026-01-01'])
        ->assertSessionHasErrors('effective_from');
    $this->actingAs($hr, 'admin')->post("/admin/attendance-policies/{$policy->id}/versions", [...$rules, 'late_grace_minutes' => 15, 'effective_from' => '2026-06-01'])
        ->assertSessionHasNoErrors();

    expect($policy->versionOn('2026-05-31')->late_grace_minutes)->toBe(5)
        ->and($policy->versionOn('2026-06-01')->late_grace_minutes)->toBe(15)
        ->and($policy->versionOn('2026-06-01')->version)->toBe(2);

    $employee = attendanceEmployee();
    $this->actingAs($hr, 'admin')->post("/admin/attendance-policies/{$policy->id}/assignments", [
        'scope_type' => 'branch', 'scope_ids' => ['branch' => $employee->branch_id], 'effective_from' => '2026-01-01',
    ])->assertSessionHasNoErrors();

    $this->actingAs($hr, 'admin')->get("/admin/attendance-policies/{$policy->id}")->assertOk()->assertSee(__('schedules.policies.version_n', ['n' => 2]));

    $resolved = app(AttendancePolicyResolver::class)
        ->for($employee, $employee->assignments()->first(), CarbonImmutable::parse('2026-07-01'));
    expect($resolved->attendance_policy_id)->toBe($policy->id)->and($resolved->late_grace_minutes)->toBe(15);
});

it('keeps system attendance statuses and their codes', function () {
    $absent = AttendanceStatus::query()->where('code', 'absent')->sole();

    $this->actingAs($this->officer, 'admin')->put("/admin/attendance-statuses/{$absent->id}", [
        'code' => 'missing', 'name_ar' => 'غائب', 'name_en' => 'Absent (no notice)', 'category' => 'absent', 'sort_order' => 1,
    ])->assertSessionHasNoErrors();
    expect($absent->fresh()->code)->toBe('absent')->and($absent->fresh()->name_en)->toBe('Absent (no notice)');

    $this->actingAs($this->officer, 'admin')->delete("/admin/attendance-statuses/{$absent->id}")->assertForbidden();

    $this->actingAs($this->officer, 'admin')->post('/admin/attendance-statuses', [
        'code' => 'training', 'name_ar' => 'تدريب', 'name_en' => 'Training', 'category' => 'present', 'counts_as_worked' => '1', 'is_paid' => '1', 'sort_order' => 70,
    ])->assertSessionHasNoErrors();
    expect(AttendanceStatus::query()->where('code', 'training')->value('counts_as_worked'))->toBeTrue();
});

it('shows only in-scope employees on the calendar and changes a date through an override', function () {
    $inScope = attendanceEmployee(['name_en' => 'Inside Person']);
    $outside = attendanceEmployee(['name_en' => 'Outside Person']);
    $day = Shift::factory()->create(['code' => 'DAY']);
    $late = Shift::factory()->between('12:00', '20:00')->create(['code' => 'LATE']);
    assignSchedule(workWeek($day), AttendanceScope::Company, null);

    $manager = adminWithRoles('Branch Manager');
    $manager->dataScopes()->create(['scope_type' => 'branch', 'scope_id' => $inScope->branch_id]);

    $this->actingAs($manager, 'admin')->get('/admin/schedule-calendar?date=2026-11-01')
        ->assertOk()->assertSee($inScope->name)->assertDontSee($outside->name)->assertSee('DAY');
    $this->actingAs($manager, 'admin')->get('/admin/schedule-calendar?date=2026-11-01&view=week')->assertOk();

    $this->actingAs($manager, 'admin')->post('/admin/schedule-calendar/override', ['employee_id' => $outside->id, 'date' => '2026-11-01', 'shift_ids' => [$late->id]])
        ->assertSessionHasErrors('employee_id');

    $this->actingAs($manager, 'admin')->get("/admin/schedule-calendar/day?employee_id={$inScope->id}&date=2026-11-01")->assertOk();
    $this->actingAs($manager, 'admin')->post('/admin/schedule-calendar/override', ['employee_id' => $inScope->id, 'date' => '2026-11-01', 'shift_ids' => [$late->id]])
        ->assertSessionHasNoErrors();
    expect(array_map(fn ($shift) => $shift->shift->code, app(ScheduleResolver::class)->for($inScope, '2026-11-01')->shifts))->toBe(['LATE']);

    $this->actingAs($manager, 'admin')->post('/admin/schedule-calendar/override', ['employee_id' => $inScope->id, 'date' => '2026-11-02']);
    expect(app(ScheduleResolver::class)->for($inScope, '2026-11-02')->isRestDay)->toBeTrue();

    $this->actingAs($manager, 'admin')->delete('/admin/schedule-calendar/override', ['employee_id' => $inScope->id, 'date' => '2026-11-01'])->assertSessionHasNoErrors();
    expect(array_map(fn ($shift) => $shift->shift->code, app(ScheduleResolver::class)->for($inScope, '2026-11-01')->shifts))->toBe(['DAY']);
});

it('runs shift changes and swaps through the approval workflow', function () {
    $alice = attendanceEmployee(['name_en' => 'Alice']);
    $bob = attendanceEmployee(['name_en' => 'Bob', 'branch_id' => $alice->branch_id, 'department_id' => $alice->department_id]);
    $day = Shift::factory()->create(['code' => 'DAY']);
    $night = Shift::factory()->between('22:00', '06:00')->create(['code' => 'NGT']);
    assignSchedule(workWeek($day), AttendanceScope::Employee, $alice->id);
    assignSchedule(workWeek($night), AttendanceScope::Employee, $bob->id);
    $approver = adminWithRoles('HR Manager');

    $this->actingAs($this->officer, 'admin')->get('/admin/schedule-changes/create')->assertOk();
    $this->actingAs($this->officer, 'admin')->post('/admin/schedule-changes', [
        'type' => 'swap', 'employee_id' => $alice->id, 'swap_employee_id' => $bob->id, 'work_date' => '2026-11-01', 'reason' => 'Family event',
    ])->assertSessionHasNoErrors();
    $this->actingAs($this->officer, 'admin')->post('/admin/schedule-changes', [
        'type' => 'change', 'employee_id' => $alice->id, 'work_date' => '2026-11-02', 'shift_id' => '', 'reason' => 'Day off',
    ])->assertSessionHasNoErrors();

    [$swap, $change] = ScheduleChangeRequest::query()->orderBy('id')->get()->all();

    // The requester (who also holds schedules.approve) cannot decide their own request.
    $this->actingAs($this->officer, 'admin')->post("/admin/schedule-changes/{$swap->id}/approve")->assertForbidden();

    $this->actingAs($approver, 'admin')->get('/admin/schedule-changes')->assertOk()->assertSee($alice->employee_number);
    $this->actingAs($approver, 'admin')->post("/admin/schedule-changes/{$swap->id}/approve")->assertSessionHasNoErrors();
    $this->actingAs($approver, 'admin')->post("/admin/schedule-changes/{$change->id}/reject")->assertSessionHasErrors('comment');
    $this->actingAs($approver, 'admin')->post("/admin/schedule-changes/{$change->id}/approve")->assertSessionHasNoErrors();

    $codes = fn ($employee, $date) => array_map(fn ($shift) => $shift->shift->code, app(ScheduleResolver::class)->for($employee, $date)->shifts);

    expect($swap->fresh()->status)->toBe(RequestStatus::Approved)
        ->and($codes($alice, '2026-11-01'))->toBe(['NGT'])
        ->and($codes($bob, '2026-11-01'))->toBe(['DAY'])
        ->and(app(ScheduleResolver::class)->for($alice, '2026-11-02')->isRestDay)->toBeTrue()
        ->and(ScheduleOverride::query()->count())->toBe(3);
});

it('renders every configuration page in Arabic and English', function (string $locale) {
    app()->setLocale($locale);
    $admin = superAdmin();
    $admin->forceFill(['locale' => $locale])->save();
    $employee = attendanceEmployee();
    $shift = Shift::factory()->create();
    $shift->breaks()->create(['type' => 'flexible', 'duration_minutes' => 30, 'is_paid' => false]);
    $schedule = workWeek($shift);
    $assignment = assignSchedule($schedule, AttendanceScope::Employee, $employee->id);
    $holiday = Holiday::create(['name_ar' => 'عيد', 'name_en' => 'Feast', 'start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'is_paid' => true, 'is_active' => true]);
    $policy = AttendancePolicy::query()->firstOrFail();
    $status = AttendanceStatus::query()->firstOrFail();

    foreach ([
        '/admin/shifts', '/admin/shifts/create', "/admin/shifts/{$shift->id}/edit",
        '/admin/work-schedules', '/admin/work-schedules/create', "/admin/work-schedules/{$schedule->id}/edit",
        '/admin/schedule-assignments', '/admin/schedule-assignments/create', "/admin/schedule-assignments/{$assignment->id}/edit",
        '/admin/schedule-calendar?date=2026-11-01', '/admin/schedule-calendar?date=2026-11-01&view=week',
        "/admin/schedule-calendar/day?employee_id={$employee->id}&date=2026-11-03",
        '/admin/schedule-changes', '/admin/schedule-changes/create',
        '/admin/holidays?year=2026', '/admin/holidays/create', "/admin/holidays/{$holiday->id}/edit",
        '/admin/attendance-policies', '/admin/attendance-policies/create', "/admin/attendance-policies/{$policy->id}",
        "/admin/attendance-policies/{$policy->id}/edit", "/admin/attendance-policies/{$policy->id}/versions/create",
        '/admin/attendance-statuses', '/admin/attendance-statuses/create', "/admin/attendance-statuses/{$status->id}/edit",
    ] as $url) {
        $this->actingAs($admin, 'admin')->get($url)->assertOk()->assertDontSee('schedules.', false)->assertDontSee('attendance.enums', false);
    }
})->with(['ar', 'en']);
