<?php

use App\Actions\Leave\ManageLeaveRequest;
use App\Enums\AttendanceScope;
use App\Enums\LeaveRequestSource;
use App\Enums\PunchDirection;
use App\Enums\RawEventSource;
use App\Enums\ScheduleType;
use App\Models\AttendanceDay;
use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyVersion;
use App\Models\AttendanceRawEvent;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\Role;
use App\Models\ScheduleAssignment;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Attendance\AttendanceEngine;
use App\Services\Attendance\AttendancePolicyResolver;
use App\Services\Attendance\Data\RawEventData;
use App\Services\Attendance\RawEventRecorder;
use App\Services\Leave\LeaveAccrualService;
use App\Services\Leave\LeaveBalanceCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\AttendanceDefaultsSeeder;
use Database\Seeders\LeaveDefaultsSeeder;
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
