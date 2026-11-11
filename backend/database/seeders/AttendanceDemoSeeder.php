<?php

namespace Database\Seeders;

use App\Actions\Attendance\ManageAttendanceCorrection;
use App\Actions\Attendance\ManageManualBatch;
use App\Actions\Attendance\ManagePeriodLock;
use App\Actions\Attendance\ManageScheduleChange;
use App\Enums\AttendanceScope;
use App\Enums\BreakMode;
use App\Enums\BreakType;
use App\Enums\CorrectionSource;
use App\Enums\MissingPunchAction;
use App\Enums\PunchDirection;
use App\Enums\RawEventSource;
use App\Enums\ScheduleType;
use App\Enums\ShiftType;
use App\Models\AttendanceDay;
use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyVersion;
use App\Models\AttendanceRawEvent;
use App\Models\AttendanceStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\Holiday;
use App\Models\ScheduleAssignment;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Approvals\ApprovalService;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\AttendanceEngine;
use App\Services\Attendance\AttendanceImporter;
use App\Services\Attendance\AttendancePolicyResolver;
use App\Services\Attendance\BulkAttendance;
use App\Services\Attendance\Data\RawEventData;
use App\Services\Attendance\RawEventRecorder;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Employees\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

/**
 * Demo attendance (never in production), built through the real services so
 * every screen shows calculated data:
 *
 * - shifts: MOR 08:00–16:30 (30 min unpaid break), EVE 16:00–00:00, NGT 22:00–06:00
 *   (night, cross-midnight), FLX flexible 07:00–19:00 (8 h, core 10:00–15:00),
 *   SPA / SPB split shift 09:00–13:00 + 17:00–21:00;
 * - schedules: STD (company, Sun–Thu MOR), FLEX (IT department), ROT (warehouse,
 *   6-day rotation), NIGHT (group WH-NIGHT), SPLIT (Alexandria sales, Sat–Thu),
 *   plus an unassigned monthly example;
 * - policies: the company "STD" policy (AttendanceDefaultsSeeder) plus
 *   "OPS" for the Alexandria branch and "IT" for the IT department;
 * - DEMO public holidays of the current year (Egyptian calendar, Islamic dates
 *   approximate — check them against the official announcement);
 * - ~5 weeks of raw events: simulated device / mobile punches with lateness,
 *   early leave, overtime, breaks, missing punches and absences; one bulk
 *   "mark all present except" batch; one Excel-style import; corrections
 *   (pending and applied); a shift change request and an approved swap; the
 *   first demo week locked.
 *
 * Simulated punches carry source_reference "DEMO-*" and payload {"demo": true}.
 */
class AttendanceDemoSeeder extends Seeder
{
    private const DAYS = 37;

    public function run(RawEventRecorder $recorder, AttendanceEngine $engine): void
    {
        if (Shift::query()->exists()) {
            return;
        }

        // The demo company is in Egypt: branch time is Cairo time.
        Branch::query()->withoutGlobalScopes()->whereNull('timezone')->update(['timezone' => 'Africa/Cairo']);

        $this->shifts();
        $this->schedules();
        $this->policies();
        $this->holidays();

        $today = AttendanceClock::date(now('Africa/Cairo'));
        $from = $today->subDays(self::DAYS);
        $yesterday = $today->subDay();
        $employees = Employee::query()->withoutGlobalScopes()->with('groups')->orderBy('id')->get();
        $resolver = app(ScheduleResolver::class);

        foreach ($employees as $employee) {
            for ($date = $from; $date->lte($yesterday); $date = $date->addDay()) {
                $this->simulateDay($recorder, $resolver, $employee, $date);
            }
        }

        foreach ($employees as $employee) {
            $engine->processRange($employee, $from, $yesterday);
        }

        $this->manualActivity($today);

        $officer = User::query()->where('email', 'attendance.officer@hrmplus.test')->first();

        if ($officer !== null) {
            app(ManagePeriodLock::class)->lock([
                'start_date' => $from->format('Y-m-d'), 'end_date' => $from->addDays(6)->format('Y-m-d'),
                'note' => 'Demo: first week closed for payroll',
            ], $officer);
        }
    }

    /**
     * @return array<string, Shift>
     */
    private function shifts(): array
    {
        $rows = [
            'MOR' => ['وردية صباحية', 'Morning', ShiftType::Fixed, '08:00', '16:30', 'chart-1', false, [['Lunch', BreakType::Fixed, '12:30', '13:00', 30, false]]],
            'EVE' => ['وردية مسائية', 'Evening', ShiftType::Fixed, '16:00', '00:00', 'chart-2', false, [['Break', BreakType::Flexible, null, null, 30, true]]],
            'NGT' => ['وردية ليلية', 'Night', ShiftType::Fixed, '22:00', '06:00', 'chart-4', true, [['Night break', BreakType::Flexible, null, null, 30, false]]],
            'FLX' => ['دوام مرن', 'Flexible', ShiftType::Flexible, '07:00', '19:00', 'chart-3', false, []],
            'SPA' => ['وردية مقسمة (صباحًا)', 'Split shift — morning', ShiftType::Fixed, '09:00', '13:00', 'chart-5', false, []],
            'SPB' => ['وردية مقسمة (مساءً)', 'Split shift — evening', ShiftType::Fixed, '17:00', '21:00', 'chart-6', false, []],
        ];
        $shifts = [];

        foreach ($rows as $code => [$ar, $en, $type, $start, $end, $color, $night, $breaks]) {
            $shift = Shift::create([
                'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'type' => $type, 'start_time' => $start, 'end_time' => $end,
                'required_minutes' => $type === ShiftType::Flexible ? 480 : null,
                'core_start_time' => $type === ShiftType::Flexible ? '10:00' : null,
                'core_end_time' => $type === ShiftType::Flexible ? '15:00' : null,
                'checkin_opens_before_minutes' => in_array($code, ['SPA', 'SPB'], true) ? 90 : 120,
                'checkout_closes_after_minutes' => in_array($code, ['SPA', 'SPB'], true) ? 120 : 240,
                'is_night' => $night, 'color' => $color, 'is_active' => true,
            ]);

            foreach ($breaks as $index => [$name, $breakType, $breakStart, $breakEnd, $minutes, $paid]) {
                $shift->breaks()->create([
                    'name' => $name, 'type' => $breakType, 'start_time' => $breakStart, 'end_time' => $breakEnd,
                    'duration_minutes' => $minutes, 'is_paid' => $paid, 'sort_order' => $index,
                ]);
            }

            $shifts[$code] = $shift;
        }

        return $shifts;
    }

    private function schedules(): void
    {
        $weekly = fn (string $code, string $ar, string $en, array $days) => $this->schedule($code, $ar, $en, ScheduleType::Weekly, $days);
        $sunToThu = fn (array $shiftCodes) => array_fill_keys([0, 1, 2, 3, 4], $shiftCodes);

        $standard = $weekly('STD', 'الدوام الرسمي (الأحد–الخميس)', 'Standard week (Sun–Thu)', $sunToThu(['MOR']));
        $flex = $weekly('FLEX', 'دوام مرن (الأحد–الخميس)', 'Flexible week (Sun–Thu)', $sunToThu(['FLX']));
        $night = $weekly('NIGHT', 'ليالي المستودع (السبت–الأربعاء)', 'Warehouse nights (Sat–Wed)', array_fill_keys([6, 0, 1, 2, 3], ['NGT']));
        $split = $weekly('SPLIT', 'وردية مقسمة للمعرض (السبت–الخميس)', 'Showroom split shift (Sat–Thu)', array_fill_keys([6, 0, 1, 2, 3, 4], ['SPA', 'SPB']));
        $rotation = $this->schedule('ROT', 'تناوب المستودع (6 أيام)', 'Warehouse rotation (6 days)', ScheduleType::Rotating,
            [0 => ['MOR'], 1 => ['MOR'], 2 => ['EVE'], 3 => ['EVE'], 4 => [], 5 => []], 6, '2026-01-04');
        $this->schedule('MON', 'جدول شهري (مثال)', 'Monthly example (1st–25th)', ScheduleType::Monthly,
            array_fill_keys(range(1, 25), ['MOR']));

        $start = '2025-01-01';
        $assign = fn (WorkSchedule $schedule, AttendanceScope $scope, ?int $id) => ScheduleAssignment::create([
            'work_schedule_id' => $schedule->id, 'scope_type' => $scope, 'scope_id' => $id, 'effective_from' => $start, 'note' => 'Demo',
        ]);

        $assign($standard, AttendanceScope::Company, null);
        $assign($flex, AttendanceScope::Department, Department::query()->withoutGlobalScopes()->where('code', 'IT')->value('id'));
        $assign($rotation, AttendanceScope::Department, Department::query()->withoutGlobalScopes()->where('code', 'OPS-WH')->value('id'));
        $assign($split, AttendanceScope::Department, Department::query()->withoutGlobalScopes()->where('code', 'SAL-ALX')->value('id'));

        if ($group = EmployeeGroup::query()->withoutGlobalScopes()->where('code', 'WH-NIGHT')->value('id')) {
            $assign($night, AttendanceScope::EmployeeGroup, $group);
        }
    }

    /**
     * @param  array<int, list<string>>  $days  pattern day index => shift codes
     */
    private function schedule(string $code, string $ar, string $en, ScheduleType $type, array $days, ?int $cycle = null, ?string $cycleStart = null): WorkSchedule
    {
        $schedule = WorkSchedule::create([
            'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'type' => $type, 'cycle_length_days' => $cycle,
            'cycle_start_date' => $cycleStart, 'is_active' => true,
            'description' => $type === ScheduleType::Monthly ? 'Demo of a day-of-month pattern; not assigned.' : null,
        ]);

        foreach ($days as $index => $codes) {
            foreach ($codes as $shiftCode) {
                $schedule->days()->create(['day_index' => $index, 'shift_id' => Shift::query()->where('code', $shiftCode)->value('id')]);
            }
        }

        return $schedule;
    }

    private function policies(): void
    {
        $defaults = app(AttendancePolicyResolver::class)->defaults()->only(AttendancePolicyVersion::RULES);

        $ops = AttendancePolicy::create(['code' => 'OPS', 'name_ar' => 'سياسة العمليات (الإسكندرية)', 'name_en' => 'Operations (Alexandria)', 'is_active' => true,
            'description' => 'Demo: 15 min grace, overtime approved automatically, missing check-out assumed at the shift end.']);
        $ops->versions()->create([...$defaults, 'version' => 1, 'effective_from' => '2025-01-01', 'late_grace_minutes' => 15,
            'late_rounding_minutes' => 5, 'overtime_requires_approval' => false, 'overtime_min_minutes' => 30, 'overtime_rounding_minutes' => 15,
            'missing_punch_action' => MissingPunchAction::AssumeSchedule, 'overtime_rate_regular' => '1.35', 'overtime_rate_rest_day' => '2.00',
            'overtime_rate_holiday' => '2.00']);
        $ops->assignments()->create(['scope_type' => AttendanceScope::Branch, 'scope_id' => Branch::query()->withoutGlobalScopes()->where('code', 'ALX')->value('id'), 'effective_from' => '2025-01-01']);

        $it = AttendancePolicy::create(['code' => 'IT', 'name_ar' => 'سياسة الدوام المرن', 'name_en' => 'Flexible hours (IT)', 'is_active' => true,
            'description' => 'Demo: breaks taken from the punches, overtime from 60 minutes, needs approval.']);
        $it->versions()->create([...$defaults, 'version' => 1, 'effective_from' => '2025-01-01', 'break_mode' => BreakMode::Actual,
            'max_break_minutes' => 60, 'overtime_min_minutes' => 60, 'overtime_requires_approval' => true]);
        $it->assignments()->create(['scope_type' => AttendanceScope::Department, 'scope_id' => Department::query()->withoutGlobalScopes()->where('code', 'IT')->value('id'), 'effective_from' => '2025-01-01']);
    }

    private function holidays(): void
    {
        $year = now()->year;
        $note = 'DEMO — verify against the official calendar of the year before relying on it.';

        foreach ([
            ['عيد الميلاد المجيد', 'Coptic Christmas', '01-07', '01-07', true],
            ['عيد الشرطة وثورة 25 يناير', 'Police Day / 25 January Revolution', '01-25', '01-25', true],
            ['عيد الفطر', 'Eid al-Fitr (approx.)', '03-20', '03-22', false],
            ['شم النسيم', 'Sham El-Nessim (approx.)', '04-13', '04-13', false],
            ['عيد تحرير سيناء', 'Sinai Liberation Day', '04-25', '04-25', true],
            ['عيد العمال', 'Labour Day', '05-01', '05-01', true],
            ['عيد الأضحى', 'Eid al-Adha (approx.)', '05-26', '05-29', false],
            ['رأس السنة الهجرية', 'Islamic New Year (approx.)', '06-16', '06-16', false],
            ['ثورة 30 يونيو', '30 June Revolution', '06-30', '06-30', true],
            ['عيد ثورة 23 يوليو', '23 July Revolution', '07-23', '07-23', true],
            ['المولد النبوي الشريف', "Prophet's Birthday (approx.)", '08-25', '08-25', false],
            ['عيد القوات المسلحة', 'Armed Forces Day', '10-06', '10-06', true],
        ] as [$ar, $en, $start, $end, $recurring]) {
            Holiday::create([
                'name_ar' => $ar, 'name_en' => $en, 'start_date' => "{$year}-{$start}", 'end_date' => "{$year}-{$end}",
                'is_recurring' => $recurring, 'is_paid' => true, 'description' => $note, 'is_active' => true,
            ]);
        }
    }

    /**
     * Realistic punches of one employee on one date (deterministic per employee and date).
     */
    private function simulateDay(RawEventRecorder $recorder, ScheduleResolver $resolver, Employee $employee, CarbonImmutable $date): void
    {
        $resolved = $resolver->for($employee, $date);

        if (! $resolved->isEmployed() || $resolved->holiday !== null) {
            return;
        }

        mt_srand($employee->id * 100_000 + (int) $date->format('ymd'));
        $mobile = $employee->groups->contains('code', 'FIELD') || ($employee->branch?->code ?? null) === 'GIZ' && mt_rand(1, 3) === 1;
        $source = $mobile ? RawEventSource::MobileGps : RawEventSource::Device;
        $reference = $mobile ? 'DEMO-APP' : 'DEMO-DEVICE-'.($employee->branch_id ?? 0);

        $punch = function (CarbonImmutable $at, PunchDirection $direction) use ($recorder, $employee, $source, $reference, $resolved): void {
            $recorder->record(RawEventData::punch(
                $source, $employee->id, $at, $direction, $resolved->timezone,
                'demo:'.$employee->id.':'.$at->getTimestamp().':'.$direction->value, sourceReference: $reference, payload: ['demo' => true],
            ), process: 'none');
        };

        if ($resolved->shifts === []) {
            // Occasional weekend work in the warehouse.
            if ($resolved->isRestDay && str_starts_with((string) $employee->department?->code, 'OPS') && mt_rand(1, 12) === 1) {
                $start = AttendanceClock::instant($date, '09:00', $resolved->timezone)->addMinutes(mt_rand(-10, 10));
                $punch($start, PunchDirection::In);
                $punch($start->addMinutes(mt_rand(180, 300)), PunchDirection::Out);
            }

            return;
        }

        $roll = mt_rand(1, 100);

        if ($roll <= 3) {
            return; // absent
        }

        foreach ($resolved->shifts as $shift) {
            $flexible = $shift->shift->isFlexible();
            $in = $flexible
                ? AttendanceClock::instant($date, '08:00', $resolved->timezone)->addMinutes(mt_rand(-50, 100))
                : $shift->startAt->addMinutes(match (true) {
                    $roll <= 11 => mt_rand(18, 55),   // late
                    default => mt_rand(-20, 9),        // on time
                });
            $out = $flexible
                ? $in->addMinutes(mt_rand(450, 560))
                : $shift->endAt->addMinutes(match (true) {
                    $roll >= 90 && $roll <= 94 => -mt_rand(25, 80), // early leave
                    $roll >= 95 => mt_rand(45, 130),                // overtime
                    default => mt_rand(-3, 25),
                });

            $punch($in, PunchDirection::In);

            // Some take their break by punching out and in again.
            if (! $flexible && $shift->shift->code === 'MOR' && mt_rand(1, 4) === 1) {
                $breakOut = AttendanceClock::instant($date, '12:30', $resolved->timezone)->addMinutes(mt_rand(-5, 5));
                $punch($breakOut, PunchDirection::Out);
                $punch($breakOut->addMinutes(mt_rand(25, 50)), PunchDirection::In);
            }

            // A forgotten check-out now and then (not for the days the bulk entry covers).
            if ($roll === 12 || $roll === 13) {
                continue;
            }

            $punch($out, PunchDirection::Out);
        }
    }

    /**
     * Bulk entry, import, corrections and schedule changes through the real actions.
     */
    private function manualActivity(CarbonImmutable $today): void
    {
        $officer = User::query()->where('email', 'attendance.officer@hrmplus.test')->first();
        $hrManager = User::query()->where('email', 'hr.manager@hrmplus.test')->first();
        $employeeAccount = User::query()->where('email', 'employee1@hrmplus.test')->first();

        if ($officer === null || $hrManager === null) {
            return;
        }

        $date = $today->subDay();

        while (! in_array($date->dayOfWeek, [0, 1, 2, 3, 4], true)) {
            $date = $date->subDay();
        }

        Auth::guard('admin')->setUser($officer);

        // 1. Bulk "mark all present except" for Cairo HR on the latest working day:
        //    clear their simulated punches first so the bulk entry is what counts.
        $hr = Department::query()->withoutGlobalScopes()->where('code', 'HR')->firstOrFail();
        $hrEmployees = Employee::query()->withoutGlobalScopes()->where('department_id', $hr->id)->employed()->orderBy('employee_number')->get();
        $recorder = app(RawEventRecorder::class);

        foreach ($hrEmployees as $employee) {
            AttendanceRawEvent::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
                ->whereBetween('occurred_at', [$date->subHours(4), $date->addHours(30)])->get()
                ->each(fn ($event) => $recorder->void($event, $officer, 'Demo: device offline, recorded by bulk entry', process: 'now'));
        }

        $statuses = AttendanceStatus::query()->pluck('id', 'code');
        $selections = [];

        foreach ($hrEmployees as $index => $employee) {
            $selections[$employee->id] = ['selected' => '1', 'action' => match ($index) {
                1 => 'status:'.$statuses['official_mission'],
                2 => 'late',
                3 => 'status:'.$statuses['absent'],
                default => 'present',
            }, 'check_in' => $index === 2 ? '09:05' : null, 'note' => $index === 1 ? 'Visit to the tax office' : null];
        }

        $bulk = app(BulkAttendance::class);
        $batch = $bulk->createDraft(['date' => $date->format('Y-m-d'), 'department_id' => $hr->id], $selections, 'Demo: device offline in HR', $officer);
        app(ManageManualBatch::class)->confirm($batch, $officer);

        // 2. Import from a spreadsheet for the Alexandria sales team (paper sign-in sheet).
        $alexSales = Employee::query()->withoutGlobalScopes()->whereHas('department', fn ($query) => $query->where('code', 'SAL-ALX'))
            ->employed()->orderBy('employee_number')->limit(3)->get();
        $importDate = $date->subDay();

        while (! in_array($importDate->dayOfWeek, [6, 0, 1, 2, 3, 4], true)) {
            $importDate = $importDate->subDay();
        }

        if ($alexSales->isNotEmpty()) {
            foreach ($alexSales as $employee) {
                AttendanceRawEvent::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
                    ->whereBetween('occurred_at', [$importDate->subHours(4), $importDate->addHours(30)])->get()
                    ->each(fn ($event) => $recorder->void($event, $officer, 'Demo: replaced by the imported sheet', process: 'now'));
            }

            $path = storage_path('app/private/demo-attendance-import.csv');
            $rows = [];

            foreach ($alexSales as $index => $employee) {
                $rows[] = [$employee->employee_number, $importDate->format('Y-m-d'), 'SPA', $index === 1 ? '09:25' : '08:58', '13:02', '', 'Paper sheet'];
                $rows[] = [$employee->employee_number, $importDate->format('Y-m-d'), 'SPB', '16:55', '21:05', '', 'Paper sheet'];
            }

            app(Spreadsheet::class)->write($path, 'csv', AttendanceImporter::COLUMNS, $rows);
            $result = app(AttendanceImporter::class)->import($path, 'csv', 'alexandria-sign-in-sheet.csv', $officer);
            @unlink($path);

            if ($result['batch'] !== null) {
                app(ManageManualBatch::class)->confirm($result['batch'], $officer);
            }
        }

        // 3. Corrections: one pending request from the portal, one applied by the officer.
        $corrections = app(ManageAttendanceCorrection::class);
        $own = $employeeAccount?->employee;

        if ($own !== null) {
            $missing = AttendanceDay::query()->withoutGlobalScopes()->where('employee_id', $own->id)
                ->where('status', 'incomplete')->latest('work_date')->first();
            $day = $missing?->work_date ?? $date->subDays(2);

            $corrections->create($own, [
                'work_date' => $day->format('Y-m-d'), 'check_out' => '16:35', 'reason' => 'I forgot to punch out before leaving.',
            ], CorrectionSource::Portal, $employeeAccount);
        }

        $late = AttendanceDay::query()->withoutGlobalScopes()->where('status', 'late')
            ->whereNull('attendance_period_lock_id')->whereHas('employee', fn ($query) => $query->where('branch_id', Branch::query()->where('code', 'CAI')->value('id')))
            ->latest('work_date')->first();

        if ($late !== null) {
            $correction = $corrections->create($late->employee, [
                'work_date' => $late->work_date->format('Y-m-d'), 'shift_id' => $late->shift_id,
                'check_in' => AttendanceClock::localTime($late->scheduled_start_at, $late->timezone),
                'reason' => 'Demo: badge reader at the gate was out of order (security log confirms arrival on time).',
            ], CorrectionSource::Admin, $officer);

            // The standard policy needs approval for corrections: HR approves it.
            if ($correction->isPending()) {
                app(ApprovalService::class)->approve($correction->latestApprovalRequest, $hrManager, 'Checked with security');
            }
        }

        // 4. Schedule changes: a pending shift change and an approved swap in the warehouse.
        $warehouse = Employee::query()->withoutGlobalScopes()->whereHas('department', fn ($query) => $query->where('code', 'OPS-WH'))
            ->employed()->orderBy('employee_number')->limit(3)->get();

        if ($warehouse->count() === 3) {
            $changes = app(ManageScheduleChange::class);
            $changes->request([
                'type' => 'change', 'employee_id' => $warehouse[0]->id, 'work_date' => $today->addDays(3)->format('Y-m-d'),
                'shift_id' => Shift::query()->where('code', 'EVE')->value('id'), 'reason' => 'Medical appointment in the morning',
            ], $officer);

            $swap = $changes->request([
                'type' => 'swap', 'employee_id' => $warehouse[1]->id, 'swap_employee_id' => $warehouse[2]->id,
                'work_date' => $today->addDays(2)->format('Y-m-d'), 'reason' => 'Family event',
            ], $officer);
            app(ApprovalService::class)->approve($swap->latestApprovalRequest, $hrManager, 'OK');
        }

        Auth::guard('admin')->forgetUser();
    }
}
