<?php

use App\Enums\AttendanceScope;
use App\Enums\OverrideSource;
use App\Enums\ScheduleType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\ScheduleOverride;
use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Settings\SettingsService;

beforeEach(function () {
    $this->employee = attendanceEmployee();
    $this->shifts = [
        'company' => Shift::factory()->create(['code' => 'CO']),
        'branch' => Shift::factory()->create(['code' => 'BR']),
        'department' => Shift::factory()->create(['code' => 'DE']),
        'group' => Shift::factory()->create(['code' => 'GR']),
        'employee' => Shift::factory()->create(['code' => 'EM']),
    ];
});

/**
 * Codes of the shifts expected on a date (fresh resolver each time).
 *
 * @return list<string>
 */
function expectedCodes(Employee $employee, string $date): array
{
    return array_map(fn ($shift) => $shift->shift->code, app(ScheduleResolver::class)->for($employee, $date)->shifts);
}

it('applies assignments with the precedence employee > group > department > branch > company', function () {
    $group = EmployeeGroup::factory()->create();
    $this->employee->groups()->attach($group);

    assignSchedule(workWeek($this->shifts['company']), AttendanceScope::Company, null);
    expect(expectedCodes($this->employee, '2026-11-01'))->toBe(['CO']);

    assignSchedule(workWeek($this->shifts['branch']), AttendanceScope::Branch, $this->employee->branch_id);
    expect(expectedCodes($this->employee, '2026-11-01'))->toBe(['BR']);

    assignSchedule(workWeek($this->shifts['department']), AttendanceScope::Department, $this->employee->department_id);
    expect(expectedCodes($this->employee, '2026-11-01'))->toBe(['DE']);

    assignSchedule(workWeek($this->shifts['group']), AttendanceScope::EmployeeGroup, $group->id);
    expect(expectedCodes($this->employee, '2026-11-01'))->toBe(['GR']);

    assignSchedule(workWeek($this->shifts['employee']), AttendanceScope::Employee, $this->employee->id);
    $resolved = app(ScheduleResolver::class)->for($this->employee, '2026-11-01');
    expect(expectedCodes($this->employee, '2026-11-01'))->toBe(['EM'])->and($resolved->source)->toBe('employee');
});

it('inherits a schedule assigned to a parent department', function () {
    $parent = Department::factory()->create(['branch_id' => $this->employee->branch_id]);
    Department::query()->whereKey($this->employee->department_id)->update(['parent_id' => $parent->id]);
    assignSchedule(workWeek($this->shifts['company']), AttendanceScope::Company, null);
    assignSchedule(workWeek($this->shifts['department']), AttendanceScope::Department, $parent->id);

    expect(expectedCodes($this->employee, '2026-11-01'))->toBe(['DE']);
});

it('respects effective dates and lets the latest start win within a level', function () {
    assignSchedule(workWeek($this->shifts['company']), AttendanceScope::Employee, $this->employee->id, '2025-01-01');
    assignSchedule(workWeek($this->shifts['branch']), AttendanceScope::Employee, $this->employee->id, '2026-11-02', '2026-11-03');

    expect(expectedCodes($this->employee, '2026-11-01'))->toBe(['CO'])
        ->and(expectedCodes($this->employee, '2026-11-02'))->toBe(['BR'])
        ->and(expectedCodes($this->employee, '2026-11-03'))->toBe(['BR'])
        ->and(expectedCodes($this->employee, '2026-11-04'))->toBe(['CO']);
});

it('lets date overrides, including a rest-day override, beat the pattern', function () {
    assignSchedule(workWeek($this->shifts['company']), AttendanceScope::Employee, $this->employee->id);
    ScheduleOverride::create(['employee_id' => $this->employee->id, 'work_date' => '2026-11-01', 'shift_id' => $this->shifts['group']->id, 'source' => OverrideSource::Manual]);
    ScheduleOverride::create(['employee_id' => $this->employee->id, 'work_date' => '2026-11-02', 'shift_id' => null, 'source' => OverrideSource::Manual]);

    $rest = app(ScheduleResolver::class)->for($this->employee, '2026-11-02');

    expect(expectedCodes($this->employee, '2026-11-01'))->toBe(['GR'])
        ->and($rest->shifts)->toBe([])->and($rest->isRestDay)->toBeTrue()->and($rest->source)->toBe('override')
        ->and(expectedCodes($this->employee, '2026-11-03'))->toBe(['CO']);
});

it('counts a rotating cycle from its start date', function () {
    $schedule = WorkSchedule::create(['code' => 'ROT', 'name_ar' => 'دوري', 'name_en' => 'Rotation', 'type' => ScheduleType::Rotating,
        'cycle_length_days' => 4, 'cycle_start_date' => '2026-11-01', 'is_active' => true]);
    // Two days on (day 0, 1), two days off.
    $schedule->days()->create(['day_index' => 0, 'shift_id' => $this->shifts['company']->id]);
    $schedule->days()->create(['day_index' => 1, 'shift_id' => $this->shifts['branch']->id]);
    assignSchedule($schedule, AttendanceScope::Employee, $this->employee->id);

    expect(expectedCodes($this->employee, '2026-11-01'))->toBe(['CO'])
        ->and(expectedCodes($this->employee, '2026-11-02'))->toBe(['BR'])
        ->and(expectedCodes($this->employee, '2026-11-03'))->toBe([])
        ->and(expectedCodes($this->employee, '2026-11-05'))->toBe(['CO'])
        ->and(expectedCodes($this->employee, '2026-11-10'))->toBe(['BR'])
        ->and(app(ScheduleResolver::class)->for($this->employee, '2026-11-04')->isRestDay)->toBeTrue();
});

it('uses a monthly pattern by day of the month', function () {
    $schedule = WorkSchedule::create(['code' => 'MON', 'name_ar' => 'شهري', 'name_en' => 'Monthly', 'type' => ScheduleType::Monthly, 'is_active' => true]);
    $schedule->days()->create(['day_index' => 15, 'shift_id' => $this->shifts['company']->id]);
    assignSchedule($schedule, AttendanceScope::Employee, $this->employee->id);

    expect(expectedCodes($this->employee, '2026-11-15'))->toBe(['CO'])
        ->and(expectedCodes($this->employee, '2026-12-15'))->toBe(['CO'])
        ->and(expectedCodes($this->employee, '2026-11-16'))->toBe([]);
});

it('falls back to the weekend rule of the settings without any schedule', function () {
    app(SettingsService::class)->set('attendance.working_days', ['monday', 'tuesday']);

    $monday = app(ScheduleResolver::class)->for($this->employee, '2026-11-02');
    $sunday = app(ScheduleResolver::class)->for($this->employee, '2026-11-01');

    expect($monday->source)->toBe('weekend_rule')->and($monday->isRestDay)->toBeFalse()->and($monday->shifts)->toBe([])
        ->and($sunday->isRestDay)->toBeTrue();
});

it('orders several shifts on one day by start time', function () {
    $evening = Shift::factory()->between('17:00', '21:00')->create(['code' => 'EVE']);
    $morning = Shift::factory()->between('07:00', '11:00')->create(['code' => 'MOR']);
    assignSchedule(weeklySchedule([0 => [$evening, $morning]]), AttendanceScope::Employee, $this->employee->id);

    expect(expectedCodes($this->employee, '2026-11-01'))->toBe(['MOR', 'EVE']);
});

it('expects nothing before the hire date', function () {
    $employee = attendanceEmployee(['hire_date' => '2026-11-05', 'original_hire_date' => '2026-11-05']);
    assignSchedule(workWeek($this->shifts['company']), AttendanceScope::Company, null);

    $before = app(ScheduleResolver::class)->for($employee, '2026-11-04');

    expect($before->isEmployed())->toBeFalse()->and($before->source)->toBe('not_employed')
        ->and(expectedCodes($employee, '2026-11-05'))->toBe(['CO']);
});
