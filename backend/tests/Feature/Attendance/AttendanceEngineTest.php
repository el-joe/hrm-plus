<?php

use App\Actions\Employees\TerminateEmployee;
use App\Enums\AttendanceDayStatus;
use App\Enums\AttendanceScope;
use App\Enums\BreakType;
use App\Enums\OvertimeStatus;
use App\Enums\PunchDirection;
use App\Enums\RawEventSource;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\AttendanceRawEvent;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Services\Attendance\Contracts\LeaveProvider;
use App\Services\Attendance\Data\LeaveDay;
use App\Services\Attendance\Data\RawEventData;
use App\Services\Attendance\RawEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->employee = attendanceEmployee();
    $this->day = Shift::factory()->between('08:00', '16:00')->create(['code' => 'DAY']);
    assignSchedule(workWeek($this->day), AttendanceScope::Company, null);
});

it('calculates a normal day from device punches in the branch timezone', function () {
    attendancePolicy();
    punchAt($this->employee, '2026-11-01 07:55', 'in');
    punchAt($this->employee, '2026-11-01 16:05', 'out');

    $day = processDay($this->employee, '2026-11-01')->sole();

    expect($day->status)->toBe(AttendanceDayStatus::Present)
        ->and($day->worked_minutes)->toBe(490)
        ->and($day->late_minutes)->toBe(0)
        ->and($day->first_in_at->toIso8601ZuluString())->toBe('2026-11-01T05:55:00Z')
        ->and($day->scheduled_start_at->toIso8601ZuluString())->toBe('2026-11-01T06:00:00Z')
        ->and($day->sources)->toBe(['device'])
        ->and($day->is_manual)->toBeFalse()
        ->and($day->calculation['policy']['rules']['late_grace_minutes'])->toBe(10);
});

it('applies grace, counting mode and rounding of lateness from the policy row', function (bool $fromStart, int $rounding, string $arrival, int $expected) {
    attendancePolicy(['late_grace_minutes' => 10, 'late_count_from_shift_start' => $fromStart, 'late_rounding_minutes' => $rounding]);
    punchAt($this->employee, "2026-11-01 {$arrival}", 'in');
    punchAt($this->employee, '2026-11-01 16:00', 'out');

    $day = processDay($this->employee, '2026-11-01')->sole();

    expect($day->late_minutes)->toBe($expected)
        ->and($day->status)->toBe($expected > 0 ? AttendanceDayStatus::Late : AttendanceDayStatus::Present);
})->with([
    'inside grace' => [true, 0, '08:10', 0],
    'from shift start' => [true, 0, '08:17', 17],
    'after grace only' => [false, 0, '08:17', 7],
    'rounded up to 15' => [true, 15, '08:17', 30],
]);

it('calculates early departure and overtime with minimum, rounding and approval from the policy', function () {
    attendancePolicy(['early_leave_grace_minutes' => 5, 'overtime_min_minutes' => 30, 'overtime_rounding_minutes' => 15, 'overtime_requires_approval' => true]);
    punchAt($this->employee, '2026-11-01 08:00', 'in');
    punchAt($this->employee, '2026-11-01 15:40', 'out');
    punchAt($this->employee, '2026-11-02 08:00', 'in');
    punchAt($this->employee, '2026-11-02 16:50', 'out');
    punchAt($this->employee, '2026-11-03 08:00', 'in');
    punchAt($this->employee, '2026-11-03 16:20', 'out');

    $early = processDay($this->employee, '2026-11-01')->sole();
    $overtime = processDay($this->employee, '2026-11-02')->sole();
    $belowMinimum = processDay($this->employee, '2026-11-03')->sole();

    expect($early->early_leave_minutes)->toBe(20)->and($early->overtime_minutes)->toBe(0)
        ->and($overtime->overtime_minutes)->toBe(45)
        ->and($overtime->overtime_status)->toBe(OvertimeStatus::Pending)
        ->and($overtime->overtime_approved_minutes)->toBeNull()
        ->and($overtime->calculation['policy']['rules']['overtime_rate_regular'])->not->toBeNull()
        ->and($belowMinimum->overtime_minutes)->toBe(0);
});

it('handles a night shift that crosses midnight, attributing both punches to the start date', function () {
    $night = Shift::factory()->between('22:00', '06:00')->create(['code' => 'NGT', 'is_night' => true]);
    assignSchedule(workWeek($night), AttendanceScope::Employee, $this->employee->id);
    attendancePolicy();

    punchAt($this->employee, '2026-11-01 21:58', 'in');
    punchAt($this->employee, '2026-11-02 06:02', 'out');

    $sunday = processDay($this->employee, '2026-11-01')->sole();
    $monday = processDay($this->employee, '2026-11-02')->sole();

    expect($sunday->shift_id)->toBe($night->id)
        ->and($sunday->status)->toBe(AttendanceDayStatus::Present)
        ->and($sunday->worked_minutes)->toBe(484)
        ->and($sunday->scheduled_end_at->toIso8601ZuluString())->toBe('2026-11-02T04:00:00Z')
        ->and($sunday->punches()->count())->toBe(2)
        ->and($monday->status)->toBe(AttendanceDayStatus::Absent)
        ->and($monday->punches()->count())->toBe(0);
});

it('pairs punches of unknown direction and deducts unpaid breaks as scheduled', function () {
    $this->day->breaks()->create(['type' => BreakType::Fixed, 'start_time' => '12:00', 'end_time' => '12:30', 'duration_minutes' => 30, 'is_paid' => false]);
    attendancePolicy(['break_mode' => 'scheduled']);
    punchAt($this->employee, '2026-11-01 08:00', 'unknown');
    punchAt($this->employee, '2026-11-01 16:00', 'unknown');

    $day = processDay($this->employee, '2026-11-01')->sole();

    expect($day->worked_minutes)->toBe(450)->and($day->break_minutes)->toBe(30)->and($day->status)->toBe(AttendanceDayStatus::Present);
});

it('takes break time from the punch gaps in actual mode and flags excess breaks', function () {
    attendancePolicy(['break_mode' => 'actual', 'max_break_minutes' => 45]);
    foreach (['08:00' => 'in', '12:00' => 'out', '13:00' => 'in', '16:00' => 'out'] as $time => $direction) {
        punchAt($this->employee, "2026-11-01 {$time}", $direction);
    }

    $day = processDay($this->employee, '2026-11-01')->sole();

    expect($day->worked_minutes)->toBe(420)->and($day->break_minutes)->toBe(60)->and($day->flags)->toContain('excess_break');
});

it('handles a missing check-out according to the policy', function (string $action, AttendanceDayStatus $status, int $worked) {
    attendancePolicy(['missing_punch_action' => $action]);
    punchAt($this->employee, '2026-11-01 08:00', 'in');

    $day = processDay($this->employee, '2026-11-01')->sole();

    expect($day->status)->toBe($status)->and($day->worked_minutes)->toBe($worked);
})->with([
    'incomplete' => ['incomplete', AttendanceDayStatus::Incomplete, 0],
    'assume scheduled end' => ['assume_schedule', AttendanceDayStatus::Present, 480],
    'absent' => ['absent', AttendanceDayStatus::Absent, 0],
]);

it('calculates a flexible shift against required minutes and core hours', function () {
    $flex = Shift::factory()->flexible('07:00', '19:00', 480, '10:00', '14:00')->create(['code' => 'FLX']);
    assignSchedule(workWeek($flex), AttendanceScope::Employee, $this->employee->id);
    attendancePolicy(['overtime_min_minutes' => 30]);

    punchAt($this->employee, '2026-11-01 07:30', 'in');
    punchAt($this->employee, '2026-11-01 14:30', 'out');
    punchAt($this->employee, '2026-11-02 10:20', 'in');
    punchAt($this->employee, '2026-11-02 19:30', 'out');

    $short = processDay($this->employee, '2026-11-01')->sole();
    $long = processDay($this->employee, '2026-11-02')->sole();

    expect($short->late_minutes)->toBe(0)->and($short->shortfall_minutes)->toBe(60)->and($short->status)->toBe(AttendanceDayStatus::Present)
        ->and($long->late_minutes)->toBe(20)->and($long->status)->toBe(AttendanceDayStatus::Late)
        ->and($long->worked_minutes)->toBe(550)->and($long->overtime_minutes)->toBe(70);
});

it('marks rest days and holidays, and counts work on them as overtime', function () {
    attendancePolicy(['rest_day_work_is_overtime' => true, 'overtime_min_minutes' => 30]);
    Holiday::create(['name_ar' => 'عيد', 'name_en' => 'Holiday', 'start_date' => '2026-11-02', 'end_date' => '2026-11-03', 'is_paid' => true, 'is_active' => true]);
    punchAt($this->employee, '2026-11-07 09:00', 'in');
    punchAt($this->employee, '2026-11-07 13:00', 'out');

    expect(processDay($this->employee, '2026-11-06')->sole()->status)->toBe(AttendanceDayStatus::RestDay)
        ->and(processDay($this->employee, '2026-11-02')->sole()->status)->toBe(AttendanceDayStatus::Holiday)
        ->and(processDay($this->employee, '2026-11-03')->sole()->holiday_id)->not->toBeNull();

    $saturday = processDay($this->employee, '2026-11-07')->sole();
    expect($saturday->status)->toBe(AttendanceDayStatus::Present)->and($saturday->flags)->toContain('rest_day_work')
        ->and($saturday->overtime_minutes)->toBe(240);
});

it('repeats a recurring holiday every year', function () {
    $holiday = Holiday::create(['name_ar' => 'عيد', 'name_en' => 'New year', 'start_date' => '2024-12-31', 'end_date' => '2025-01-01', 'is_recurring' => true, 'is_paid' => true, 'is_active' => true]);

    expect($holiday->covers(Carbon::parse('2026-12-31')))->toBeTrue()
        ->and($holiday->covers(Carbon::parse('2027-01-01')))->toBeTrue()
        ->and($holiday->covers(Carbon::parse('2026-12-30')))->toBeFalse()
        ->and($holiday->covers(Carbon::parse('2023-12-31')))->toBeFalse();
});

it('is idempotent: processing twice gives the identical result and writes nothing', function () {
    attendancePolicy();
    punchAt($this->employee, '2026-11-01 08:20', 'in');
    punchAt($this->employee, '2026-11-01 08:20', 'in'); // double tap
    punchAt($this->employee, '2026-11-01 16:00', 'out');

    $first = processDay($this->employee, '2026-11-01')->sole();
    $updatedAt = $first->updated_at;
    $this->travel(5)->minutes();
    $second = processDay($this->employee, '2026-11-01')->sole();

    expect($second->id)->toBe($first->id)
        ->and($second->calculation_hash)->toBe($first->calculation_hash)
        ->and($second->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(AttendanceDay::query()->count())->toBe(1)
        ->and(AttendancePunch::query()->where('is_duplicate', true)->count())->toBe(1)
        ->and($second->late_minutes)->toBe(20);
});

it('rejects a duplicate raw event at the database level and returns the stored one', function () {
    $recorder = app(RawEventRecorder::class);
    $data = RawEventData::punch(RawEventSource::Device, $this->employee->id, Carbon::parse('2026-11-01 06:00'), PunchDirection::In, 'Africa/Cairo', 'device:SN1:7:1700000000');

    $first = $recorder->record($data, process: 'none');
    $again = $recorder->record($data, process: 'none');

    expect($again->id)->toBe($first->id)->and($again->wasRecentlyCreated)->toBeFalse()
        ->and(AttendanceRawEvent::query()->count())->toBe(1)
        ->and(AttendancePunch::query()->count())->toBe(1);

    expect(fn () => AttendanceRawEvent::query()->insert([
        'kind' => 'punch', 'source' => 'device', 'employee_id' => $this->employee->id, 'occurred_at' => now(), 'timezone' => 'UTC',
        'direction' => 'in', 'idempotency_key' => 'device:SN1:7:1700000000', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('expects no attendance before hire or after termination', function () {
    $employee = attendanceEmployee(['hire_date' => '2026-11-02', 'original_hire_date' => '2026-11-02']);
    attendancePolicy();

    expect(processDay($employee, '2026-11-01'))->toBeEmpty()
        ->and(processDay($employee, '2026-11-02')->sole()->status)->toBe(AttendanceDayStatus::Absent);

    app(TerminateEmployee::class)->handle($employee, [
        'termination_date' => '2026-11-03', 'termination_type' => 'resignation', 'reason' => 'Leaving',
    ]);

    expect(processDay($employee, '2026-11-03'))->toHaveCount(1)
        ->and(processDay($employee, '2026-11-04'))->toBeEmpty();
});

it('uses approved leave from the leave provider hook', function () {
    attendancePolicy();
    app()->bind(LeaveProvider::class, fn () => new class implements LeaveProvider
    {
        public function leaveOn(Employee $employee, CarbonImmutable $date): ?LeaveDay
        {
            return $date->format('Y-m-d') === '2026-11-01' ? new LeaveDay('leave_request:9', 'ANNUAL') : null;
        }
    });

    $day = processDay($this->employee, '2026-11-01')->sole();

    expect($day->status)->toBe(AttendanceDayStatus::OnLeave)->and($day->leave_reference)->toBe('leave_request:9');
});
