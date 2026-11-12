<?php

namespace App\Services\Employees;

use App\Enums\CorrectionStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\LoanStatus;
use App\Enums\PerformanceReviewStatus;
use App\Enums\TaskAssignee;
use App\Enums\TaskStatus;
use App\Models\AssetAssignment;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LeaveRequest;
use App\Models\OnboardingTask;
use App\Models\Payslip;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Announcements\AnnouncementFeed;
use App\Services\Assets\PortalAssets;
use App\Services\Attendance\AttendanceTimesheet;
use App\Services\Attendance\BulkAttendance;
use App\Services\Attendance\Data\ResolvedSchedule;
use App\Services\Attendance\Data\ScheduledShift;
use App\Services\Attendance\HolidayCalendar;
use App\Services\Attendance\Mobile\MobileAttendanceStatus;
use App\Services\Attendance\ScheduleResolver;
use App\Services\HrRequests\PortalHrRequests;
use App\Services\Leave\Data\LeaveBalance;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeaveEligibility;
use App\Services\Notifications\NotificationInbox;
use App\Services\Onboarding\PortalOnboarding;
use App\Services\Payroll\PortalPayslips;
use App\Services\Performance\PortalPerformance;

/**
 * The employee home (portal "Home" and API GET /api/v1/me/home): every card in one
 * place, computed from the same services the module pages use — today's attendance,
 * this month's totals, leave balances, pending items, the latest payslip, upcoming
 * shifts and holidays, and the tasks waiting for the employee. Plain arrays of raw
 * values (dates Y-m-d, money DECIMAL strings, minutes as ints); the views format them.
 *
 * Always the given employee's own records (no admin data scope applies).
 */
class PortalHome
{
    /**
     * Days of upcoming schedule shown on the home card.
     */
    public const UPCOMING_DAYS = 7;

    /**
     * Days ahead searched for public holidays.
     */
    public const HOLIDAY_DAYS = 60;

    public function __construct(
        private readonly MobileAttendanceStatus $attendanceStatus,
        private readonly AttendanceTimesheet $timesheet,
        private readonly ScheduleResolver $schedules,
        private readonly HolidayCalendar $holidays,
        private readonly LeaveBalanceCalculator $balances,
        private readonly LeaveEligibility $eligibility,
        private readonly PortalHrRequests $requests,
        private readonly AnnouncementFeed $announcements,
        private readonly PortalPayslips $payslips,
        private readonly PortalAssets $assets,
        private readonly PortalPerformance $performance,
        private readonly PortalOnboarding $onboarding,
        private readonly NotificationInbox $inbox,
    ) {}

    /**
     * @return array{date: string, timezone: string, state: string, next_action: string, employed: bool, is_working_day: bool, is_rest_day: bool, holiday: ?string, within_shift_window: bool, shifts: list<array{name: string, start_local: string, end_local: string}>, check_in_local: ?string, check_out_local: ?string, status: ?string, status_label: ?string, mobile_checkin_enabled: bool, face_mode: string, pending_reviews: int}
     *
     * @scramble-return array{date: string, timezone: string, state: string, next_action: string, employed: bool, is_working_day: bool, is_rest_day: bool, holiday: ?string, within_shift_window: bool, shifts: list<array{name: string, start_local: string, end_local: string}>, check_in_local: ?string, check_out_local: ?string, status: ?string, status_label: ?string, mobile_checkin_enabled: bool, face_mode: string, pending_reviews: int}
     */
    public function attendanceToday(Employee $employee): array
    {
        $today = $this->attendanceStatus->today($employee);
        $punches = collect($today['punches']);
        $day = $today['days']->first();

        return [
            'date' => $today['date'],
            'timezone' => $today['timezone'],
            'state' => $today['state'],
            'next_action' => $today['next_action'],
            'employed' => $today['employed'],
            'is_working_day' => $today['is_working_day'],
            'is_rest_day' => $today['is_rest_day'],
            'holiday' => $today['holiday'],
            'within_shift_window' => $today['within_shift_window'],
            'shifts' => array_map(fn (array $shift): array => [
                'name' => $shift['name'], 'start_local' => $shift['start_local'], 'end_local' => $shift['end_local'],
            ], $today['shifts']),
            'check_in_local' => $punches->firstWhere('direction', 'in')['local'] ?? null,
            'check_out_local' => $punches->where('direction', 'out')->last()['local'] ?? null,
            'status' => $day?->status?->value,
            'status_label' => $day?->status?->label(),
            'mobile_checkin_enabled' => (bool) $today['rules']['mobile_checkin_enabled'],
            'face_mode' => $today['face']['mode'],
            'pending_reviews' => $today['pending_reviews'],
        ];
    }

    /**
     * @return array{month: string, present_days: int, late_days: int, absent_days: int, leave_days: int, incomplete_days: int, missing_punches: int, worked_minutes: int, scheduled_minutes: int, late_minutes: int, overtime_minutes: int}
     *
     * @scramble-return array{month: string, present_days: int, late_days: int, absent_days: int, leave_days: int, incomplete_days: int, missing_punches: int, worked_minutes: int, scheduled_minutes: int, late_minutes: int, overtime_minutes: int}
     */
    public function attendanceMonth(Employee $employee): array
    {
        [$from, $to] = AttendanceTimesheet::month(null);
        $summary = $this->timesheet->summary($this->timesheet->days($employee, $from, $to));

        return [
            'month' => $from->format('Y-m'),
            ...array_intersect_key($summary, array_flip([
                'present_days', 'late_days', 'absent_days', 'leave_days', 'incomplete_days', 'missing_punches',
                'worked_minutes', 'scheduled_minutes', 'late_minutes', 'overtime_minutes',
            ])),
        ];
    }

    /**
     * Balances of the requestable leave types that track a balance (current leave year).
     *
     * @return list<LeaveBalance>
     */
    public function leaveBalances(Employee $employee): array
    {
        return $this->balances->all($employee, null, $this->eligibility->requestableTypes($employee))
            ->filter->tracksBalance()->values()->all();
    }

    /**
     * @return array{leave_requests: int, hr_requests: int, loan_requests: int, attendance_corrections: int, announcements_to_acknowledge: int}
     *
     * @scramble-return array{leave_requests: int, hr_requests: int, loan_requests: int, attendance_corrections: int, announcements_to_acknowledge: int}
     */
    public function pending(Employee $employee): array
    {
        return [
            'leave_requests' => LeaveRequest::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
                ->inStatus([LeaveRequestStatus::Pending, LeaveRequestStatus::CancellationPending])->count(),
            'hr_requests' => $this->requests->openCount($employee),
            'loan_requests' => EmployeeLoan::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
                ->where('status', LoanStatus::Pending)->count(),
            'attendance_corrections' => AttendanceCorrection::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
                ->where('status', CorrectionStatus::Pending)->count(),
            'announcements_to_acknowledge' => $this->announcements->pendingAcknowledgements($employee),
        ];
    }

    public function latestPayslip(Employee $employee): ?Payslip
    {
        return $this->payslips->payslips($employee)->first();
    }

    /**
     * Shifts of the next days (tomorrow onwards) and public holidays ahead.
     *
     * @return array{shifts: list<array{date: string, name: string, start_local: string, end_local: string}>, rest_days: list<string>, holidays: list<array{date: string, name: string}>}
     *
     * @scramble-return array{shifts: list<array{date: string, name: string, start_local: string, end_local: string}>, rest_days: list<string>, holidays: list<array{date: string, name: string}>}
     */
    public function upcoming(Employee $employee): array
    {
        $tomorrow = BulkAttendance::today()->addDay();
        $shifts = [];
        $restDays = [];

        /** @var ResolvedSchedule $day */
        foreach ($this->schedules->forRange($employee, $tomorrow, $tomorrow->addDays(self::UPCOMING_DAYS - 1)) as $date => $day) {
            if (! $day->isEmployed()) {
                continue;
            }

            if ($day->shifts === [] || $day->holiday !== null) {
                $restDays[] = (string) $date;

                continue;
            }

            foreach ($day->shifts as $shift) {
                /** @var ScheduledShift $shift */
                $shifts[] = [
                    'date' => (string) $date,
                    'name' => $shift->shift->name,
                    'start_local' => $shift->startAt->setTimezone($day->timezone)->format('H:i'),
                    'end_local' => $shift->endAt->setTimezone($day->timezone)->format('H:i'),
                ];
            }
        }

        $holidays = [];
        $seen = [];

        foreach ($this->holidays->between(BulkAttendance::today(), BulkAttendance::today()->addDays(self::HOLIDAY_DAYS), $employee->branch_id) as $date => $holiday) {
            // A multi-day holiday is listed once, on its first day.
            if (isset($seen[$holiday->id])) {
                continue;
            }

            $seen[$holiday->id] = true;
            $holidays[] = ['date' => $date, 'name' => $holiday->name];
        }

        return ['shifts' => $shifts, 'rest_days' => $restDays, 'holidays' => array_slice($holidays, 0, 5)];
    }

    /**
     * Things waiting for the employee: onboarding tasks, assets and reviews to acknowledge, self-assessments.
     *
     * @return list<array{kind: string, id: int, title: string, due_date: ?string}>
     *
     * @scramble-return list<array{kind: string, id: int, title: string, due_date: ?string}>
     */
    public function tasks(Employee $employee, int $limit = 10): array
    {
        $tasks = [];
        $onboarding = $this->onboarding->current($employee);

        if ($onboarding !== null) {
            $onboarding->tasks->where('assignee', TaskAssignee::Employee)->where('status', TaskStatus::Pending)
                ->sortBy(fn (OnboardingTask $task): string => $task->due_date?->format('Y-m-d') ?? '9999-12-31')
                ->each(function (OnboardingTask $task) use (&$tasks): void {
                    $tasks[] = ['kind' => 'onboarding_task', 'id' => $task->id, 'title' => (string) $task->title, 'due_date' => $task->due_date?->toDateString()];
                });
        }

        $this->assets->assignments($employee)->with('asset')->get()
            ->filter(fn (AssetAssignment $assignment): bool => $assignment->isActive() && ! $assignment->isAcknowledged())
            ->each(function (AssetAssignment $assignment) use (&$tasks): void {
                $tasks[] = ['kind' => 'acknowledge_asset', 'id' => $assignment->id, 'title' => (string) $assignment->asset?->name, 'due_date' => null];
            });

        $this->performance->reviews($employee)
            ->whereIn('status', [PerformanceReviewStatus::SelfAssessment, PerformanceReviewStatus::Acknowledgement])
            ->with('cycle')->get()
            ->each(function (PerformanceReview $review) use (&$tasks): void {
                $self = $review->status === PerformanceReviewStatus::SelfAssessment;
                $tasks[] = [
                    'kind' => $self ? 'self_assessment' : 'acknowledge_review',
                    'id' => $review->id,
                    'title' => (string) $review->cycle?->name,
                    'due_date' => $self ? $review->cycle?->self_assessment_due?->toDateString() : null,
                ];
            });

        return array_slice($tasks, 0, $limit);
    }

    /**
     * Unread notifications and announcements of the account.
     *
     * @return array{announcements: int, notifications: int}
     *
     * @scramble-return array{announcements: int, notifications: int}
     */
    public function unread(Employee $employee, User $user): array
    {
        return [
            'announcements' => $this->announcements->unreadCount($employee),
            'notifications' => $this->inbox->unreadCount($user),
        ];
    }
}
