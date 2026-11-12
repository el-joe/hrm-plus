<?php

namespace App\Services\Dashboard;

use App\Enums\ApplicationStatus;
use App\Enums\AttendanceDayStatus;
use App\Enums\EmployeeStatus;
use App\Enums\HrRequestStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\StageCategory;
use App\Enums\UserStatus;
use App\Enums\VacancyStatus;
use App\Models\AttendanceDay;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\HrRequest;
use App\Models\JobApplication;
use App\Models\LeaveRequest;
use App\Models\LoginHistory;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Models\Vacancy;
use App\Services\Attendance\BulkAttendance;
use App\Services\Attendance\Reports\AttendanceDashboard;
use App\Services\Authorization\DataScopeManager;
use App\Services\HrRequests\HrRequestVisibility;
use App\Services\Reports\Definitions\Employees\EmployeeDistributionReport;
use App\Services\Reports\Definitions\Employees\EmployeesByBranchReport;
use App\Services\Reports\Definitions\Employees\EmployeesByDepartmentReport;
use App\Services\Reports\Definitions\Employees\EmployeeTurnoverReport;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use App\Support\Charts\ChartData;
use App\Support\Formatter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The data of every dashboard widget (App\Services\Dashboard\WidgetRegistry), from live aggregate
 * queries inside the admin's data scope. Each method returns a plain, cacheable array:
 *
 *   stat   {type, value, hint?, url?}
 *   list   {type, count, items: [{label, meta, value?, url?}], url?}
 *   chart  {type, chart (ChartData), summary?: [{label, value}], url?}
 *   kpis   {type, items: [{label, value}], hint?, url?}
 *
 * Values are already formatted for display (Formatter: grouping, Arabic-Indic digits, money);
 * drill-down URLs carry the dashboard filters into the matching report or list.
 *
 * Definitions follow the "notes for 8c" of each phase (PROJECT_PLAN.md): live pay = regular pay slot
 * of finalised runs; HR request open set; taken leave statuses; attendance classification of
 * AttendanceDashboard.
 */
class DashboardWidgets
{
    /** @var array<string, array<string, int>> */
    private array $todayCounts = [];

    public function __construct(
        private readonly AttendanceDashboard $attendance,
        private readonly ReportRegistry $reports,
        private readonly DataScopeManager $scopes,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function totalEmployees(array $filters, User $user): array
    {
        return $this->stat($this->employees($filters)->where('employees.status', '!=', EmployeeStatus::Terminated->value)->count(),
            url: $this->reportUrl($user, 'employee_directory', $filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function activeEmployees(array $filters, User $user): array
    {
        return $this->stat($this->employees($filters)->where('employees.status', EmployeeStatus::Active->value)->count(),
            url: $this->reportUrl($user, 'employee_directory', [...$filters, 'employee_status' => 'active']));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function inactiveEmployees(array $filters, User $user): array
    {
        return $this->stat($this->employees($filters)->where('employees.status', EmployeeStatus::Inactive->value)->count(),
            url: $this->reportUrl($user, 'employee_directory', [...$filters, 'employee_status' => 'inactive']));
    }

    /**
     * Distinct employees on taken (approved / cancellation-pending) leave today.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function employeesOnLeave(array $filters, User $user): array
    {
        $today = $this->today();
        $query = LeaveRequest::query()->inStatus(LeaveRequestStatus::taken())->overlapping($today, $today);
        $count = (int) ReportFilters::applyEmployeeFilters($query, 'leave_requests.employee_id', $this->employeeFilters($filters))
            ->distinct()->count('leave_requests.employee_id');

        return $this->stat($count, __('dashboard.hints.today'), $this->reportUrl($user, 'employees_on_leave', [...$filters, 'date' => $today], withRange: false));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function absentToday(array $filters, User $user): array
    {
        $counts = $this->todayCounts($filters);

        return $this->stat($counts['absent'], __('dashboard.hints.of_scheduled', ['count' => Formatter::number($counts['expected'])]),
            $this->reportUrl($user, 'absence', [...$filters, 'from' => $this->today(), 'to' => $this->today()]));
    }

    /**
     * Checked in today without a check-out yet; the hint gives everyone who came in today.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function presentNow(array $filters, User $user): array
    {
        $counts = $this->todayCounts($filters);

        return $this->stat($counts['checked_in_now'], __('dashboard.hints.came_in_today', ['count' => Formatter::number($counts['present'] + $counts['late'])]),
            $this->reportUrl($user, 'daily_attendance', [...$filters, 'from' => $this->today(), 'to' => $this->today()]));
    }

    /**
     * Late days in the selected period (attendance_days.late_minutes > 0); the hint gives today.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function lateArrivals(array $filters, User $user): array
    {
        $count = $this->days($filters)->where('attendance_days.late_minutes', '>', 0)->count();

        return $this->stat($count, __('dashboard.hints.today_count', ['count' => Formatter::number($this->todayCounts($filters)['late'])]),
            $this->reportUrl($user, 'late_arrivals', $filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function pendingLeaveRequests(array $filters, User $user): array
    {
        $query = LeaveRequest::query()->where('leave_requests.status', LeaveRequestStatus::Pending->value);
        $count = ReportFilters::applyEmployeeFilters($query, 'leave_requests.employee_id', $this->employeeFilters($filters))->count();
        $url = $user->can('leaves.view') ? route('admin.leave-requests.index', ['status' => LeaveRequestStatus::Pending->value])
            : $this->reportUrl($user, 'leave_requests', [...$filters, 'status' => LeaveRequestStatus::Pending->value]);

        return $this->stat($count, null, $url);
    }

    /**
     * HR requests in the open set (pending approval, open, in progress, awaiting employee); hint: overdue.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function pendingHrRequests(array $filters, User $user): array
    {
        $open = [HrRequestStatus::PendingApproval->value, HrRequestStatus::Open->value, HrRequestStatus::InProgress->value, HrRequestStatus::AwaitingEmployee->value];
        $query = HrRequest::query()->whereIn('hr_requests.status', $open);
        $query = ReportFilters::applyEmployeeFilters($query, 'hr_requests.employee_id', $this->employeeFilters($filters),
            ['branch' => 'hr_requests.branch_id', 'department' => 'hr_requests.department_id']);

        if ($user->can('hr_requests.view') && ! $user->can('hr_requests.manage')) {
            app(HrRequestVisibility::class)->constrain($query, $user);
        }

        $counts = $query->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when hr_requests.status != 'awaiting_employee' and hr_requests.due_at is not null and hr_requests.due_at < ? then 1 else 0 end) as overdue", [now()])
            ->first();
        $url = $user->can('hr_requests.view') ? route('admin.hr-requests.index') : $this->reportUrl($user, 'hr_requests', $filters);

        return $this->stat((int) ($counts->total ?? 0), __('dashboard.hints.overdue', ['count' => Formatter::number((int) ($counts->overdue ?? 0))]), $url);
    }

    /**
     * Candidates added in the period (scoped and filtered through their applications' vacancies).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function newCandidates(array $filters, User $user): array
    {
        $query = Candidate::query()->whereBetween('candidates.created_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59']);

        if (($filters['branch_id'] ?? null) || ($filters['department_id'] ?? null)) {
            $query->whereHas('applications.vacancy', fn (Builder $query) => $this->vacancyFilters($query, $filters));
        }

        return $this->stat($query->count(), __('dashboard.hints.in_period'), $this->reportUrl($user, 'applications', $filters));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function openVacancies(array $filters, User $user): array
    {
        $row = $this->vacancyFilters(Vacancy::query()->where('vacancies.status', VacancyStatus::Open->value), $filters)
            ->toBase()->selectRaw('count(*) as total, coalesce(sum(greatest(vacancies.openings - vacancies.filled_count, 0)), 0) as remaining')->first();
        $url = $user->can('recruitment.view') ? route('admin.vacancies.index', ['status' => VacancyStatus::Open->value])
            : $this->reportUrl($user, 'vacancies', [...$filters, 'vacancy_status' => VacancyStatus::Open->value], withRange: false);

        return $this->stat((int) ($row->total ?? 0), __('dashboard.hints.openings_left', ['count' => Formatter::number((int) ($row->remaining ?? 0))]), $url);
    }

    /**
     * Current contracts (not terminated, not renewed) of employed staff ending in the next 60 days.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function contractExpirations(array $filters, User $user): array
    {
        $from = $this->today();
        $to = CarbonImmutable::parse($from)->addDays(60)->format('Y-m-d');
        $query = EmployeeContract::query()
            ->with(['employee' => fn ($query) => $query->select('id', 'employee_number', 'name_ar', 'name_en')])
            ->whereBetween('employee_contracts.end_date', [$from, $to])
            ->whereNull('employee_contracts.terminated_on')
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('employee_contracts as renewals')->whereColumn('renewals.renewal_of_id', 'employee_contracts.id'))
            ->whereHas('employee', fn (Builder $query) => $query->where('status', '!=', EmployeeStatus::Terminated->value));
        $query = ReportFilters::applyEmployeeFilters($query, 'employee_contracts.employee_id', $this->employeeFilters($filters));

        $count = (clone $query)->count();
        $items = $query->orderBy('employee_contracts.end_date')->limit(5)->get()->map(fn (EmployeeContract $contract): array => [
            'label' => $contract->employee?->name ?? '—',
            'meta' => Formatter::digits($contract->employee?->employee_number),
            'value' => Formatter::date($contract->end_date),
            'url' => $user->can('employees.view') && $contract->employee ? route('admin.employees.tab', [$contract->employee_id, 'contracts']) : null,
        ])->all();

        return ['type' => 'list', 'count' => Formatter::number($count), 'items' => $items, 'hint' => __('dashboard.hints.next_days', ['days' => Formatter::number(60)]),
            'url' => $this->reportUrl($user, 'contract_expiration', [...$filters, 'from' => $from, 'to' => $to])];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function upcomingBirthdays(array $filters, User $user): array
    {
        return $this->upcoming($filters, $user, 'date_of_birth', false);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function upcomingAnniversaries(array $filters, User $user): array
    {
        return $this->upcoming($filters, $user, 'hire_date', true);
    }

    /**
     * Live pay of the latest finalised period ending on or before the end of the selected range:
     * employees paid, gross, deductions, net, employer cost.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function payrollSummary(array $filters, User $user): array
    {
        $latest = $this->liveItems($filters)->where('payroll_periods.end_date', '<=', $filters['to'])
            ->orderByDesc('payroll_periods.end_date')->value('payroll_periods.id');

        if ($latest === null) {
            return ['type' => 'kpis', 'items' => [], 'hint' => __('dashboard.empty.no_finalised_payroll'), 'url' => null];
        }

        $row = $this->liveItems($filters)->where('payroll_items.payroll_period_id', $latest)->toBase()
            ->selectRaw('max(payroll_periods.name_ar) as name_ar, max(payroll_periods.name_en) as name_en, count(distinct payroll_items.employee_id) as employees')
            ->selectRaw('sum(payroll_items.gross_pay) as gross, sum(payroll_items.total_deductions) as deductions, sum(payroll_items.net_pay) as net, sum(payroll_items.employer_contributions) as employer')
            ->first();
        $period = app()->getLocale() === 'en' ? ($row->name_en ?: $row->name_ar) : ($row->name_ar ?: $row->name_en);

        return [
            'type' => 'kpis',
            'hint' => $period.' · '.__('dashboard.hints.employees_paid', ['count' => Formatter::number((int) $row->employees)]),
            'items' => [
                ['label' => __('reports.columns.gross'), 'value' => Formatter::money((string) $row->gross)],
                ['label' => __('reports.columns.deductions'), 'value' => Formatter::money((string) $row->deductions)],
                ['label' => __('reports.columns.net'), 'value' => Formatter::money((string) $row->net)],
                ['label' => __('reports.columns.employer_cost'), 'value' => Formatter::money((string) $row->employer)],
            ],
            'url' => $this->reportUrl($user, 'payroll_summary', [...$filters, 'payroll_period_id' => $latest]),
        ];
    }

    /**
     * Gross, net and employer cost of the last 12 finalised periods ending in or before the range end.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function monthlySalaryExpenses(array $filters, User $user): array
    {
        $rows = $this->liveItems($filters)->where('payroll_periods.end_date', '<=', $filters['to'])->toBase()
            ->select('payroll_periods.id', 'payroll_periods.end_date', 'payroll_periods.code')
            ->selectRaw('sum(payroll_items.gross_pay) as gross, sum(payroll_items.net_pay) as net, sum(payroll_items.employer_contributions) as employer')
            ->groupBy('payroll_periods.id', 'payroll_periods.end_date', 'payroll_periods.code')
            ->orderByDesc('payroll_periods.end_date')->limit(12)->get()->reverse()->values();

        return [
            'type' => 'chart',
            'chart' => ChartData::make('bar', $rows->map(fn (object $row): string => CarbonImmutable::parse($row->end_date)->format('Y-m'))->all(), [
                ['label' => __('reports.columns.gross'), 'data' => $rows->pluck('gross')->all(), 'color' => 'chart-1'],
                ['label' => __('reports.columns.net'), 'data' => $rows->pluck('net')->all(), 'color' => 'chart-2'],
                ['label' => __('reports.columns.employer_cost'), 'data' => $rows->pluck('employer')->all(), 'color' => 'chart-4'],
            ]),
            'url' => $this->reportUrl($user, 'payroll_summary', [...$filters, 'from' => CarbonImmutable::parse($filters['to'])->subMonths(11)->startOfMonth()->format('Y-m-d')]),
        ];
    }

    /**
     * Employed head count per branch (active / inactive) — the employees_by_branch report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function branchComparison(array $filters, User $user): array
    {
        $rows = app(EmployeesByBranchReport::class)->query([...$this->employeeFilters($filters), 'branch_id' => null, 'from' => $filters['from'], 'to' => $filters['to']], $user);

        return $this->placementChart($rows, $this->reportUrl($user, 'employees_by_branch', $filters));
    }

    /**
     * Employed head count per department (largest ten) — the employees_by_department report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function departmentComparison(array $filters, User $user): array
    {
        $rows = app(EmployeesByDepartmentReport::class)->query([...$this->employeeFilters($filters), 'department_id' => null, 'from' => $filters['from'], 'to' => $filters['to']], $user)
            ->sortByDesc('employed')->take(10)->values();

        return $this->placementChart($rows, $this->reportUrl($user, 'employees_by_department', $filters));
    }

    /**
     * Applications received in the period by the category of their current stage, plus hires and
     * the hire rate.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function recruitmentStatistics(array $filters, User $user): array
    {
        $query = JobApplication::query()
            ->join('vacancies', 'vacancies.id', '=', 'job_applications.vacancy_id')
            ->leftJoin('recruitment_stages', 'recruitment_stages.id', '=', 'job_applications.stage_id')
            ->whereBetween('job_applications.applied_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59']);
        $byCategory = $this->vacancyFilters($query, $filters)->toBase()
            ->select('recruitment_stages.category')->selectRaw('count(*) as total')
            ->groupBy('recruitment_stages.category')->pluck('total', 'category');
        $total = (int) $byCategory->sum();
        $hired = (int) $this->vacancyFilters(JobApplication::query()->join('vacancies', 'vacancies.id', '=', 'job_applications.vacancy_id')
            ->where('job_applications.status', ApplicationStatus::Hired->value)
            ->whereBetween('job_applications.hired_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59']), $filters)->count();

        $values = [];

        foreach (StageCategory::cases() as $category) {
            $values[$category->label()] = (int) ($byCategory[$category->value] ?? 0);
        }

        return [
            'type' => 'chart',
            'chart' => ChartData::series('bar', __('reports.columns.applications'), $values),
            'summary' => [
                ['label' => __('reports.columns.applications'), 'value' => Formatter::number($total)],
                ['label' => __('dashboard.labels.hires_in_period'), 'value' => Formatter::number($hired)],
                ['label' => __('reports.columns.hire_rate'), 'value' => Formatter::percent($total > 0 ? $hired * 100 / $total : 0)],
            ],
            'url' => $this->reportUrl($user, 'candidate_pipeline', $filters),
        ];
    }

    /**
     * Processed attendance per day of the period (last 31 days of it): present, late, absent, on leave.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function attendanceTrends(array $filters, User $user): array
    {
        $to = CarbonImmutable::parse($filters['to']);
        $from = CarbonImmutable::parse($filters['from'])->max($to->subDays(30));
        $rows = $this->days([...$filters, 'from' => $from->format('Y-m-d')])->toBase()
            ->select('attendance_days.work_date', 'attendance_days.status')->selectRaw('count(distinct attendance_days.employee_id) as total')
            ->whereIn('attendance_days.status', [AttendanceDayStatus::Present->value, AttendanceDayStatus::Late->value, AttendanceDayStatus::Absent->value, AttendanceDayStatus::OnLeave->value])
            ->groupBy('attendance_days.work_date', 'attendance_days.status')->get();

        $dates = [];

        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $dates[] = $day->format('Y-m-d');
        }

        $series = fn (AttendanceDayStatus $status): array => array_map(
            fn (string $date): int => (int) ($rows->first(fn (object $row): bool => substr((string) $row->work_date, 0, 10) === $date && $row->status === $status->value)?->total ?? 0),
            $dates,
        );

        return [
            'type' => 'chart',
            'chart' => ChartData::make('line', array_map(fn (string $date): string => substr($date, 5), $dates), [
                ['label' => AttendanceDayStatus::Present->label(), 'data' => $series(AttendanceDayStatus::Present), 'color' => 'chart-1'],
                ['label' => AttendanceDayStatus::Late->label(), 'data' => $series(AttendanceDayStatus::Late), 'color' => 'chart-5'],
                ['label' => AttendanceDayStatus::Absent->label(), 'data' => $series(AttendanceDayStatus::Absent), 'color' => 'chart-4'],
                ['label' => AttendanceDayStatus::OnLeave->label(), 'data' => $series(AttendanceDayStatus::OnLeave), 'color' => 'chart-3'],
            ]),
            'url' => $this->reportUrl($user, 'monthly_attendance', [...$filters, 'from' => $from->format('Y-m-d')]),
        ];
    }

    /**
     * Hires and terminations of the 12 months ending with the range end, and the turnover rate over
     * those months (terminations ÷ average head count) — the employee_turnover report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function employeeTurnover(array $filters, User $user): array
    {
        $from = CarbonImmutable::parse($filters['to'])->subMonths(11)->startOfMonth()->format('Y-m-d');
        $rows = app(EmployeeTurnoverReport::class)->query([...$this->employeeFilters($filters), 'from' => $from, 'to' => $filters['to']], $user);
        $average = $rows->isEmpty() ? 0 : ($rows->first()['opening'] + $rows->last()['closing']) / 2;
        $terminations = (int) $rows->sum('terminations');

        return [
            'type' => 'chart',
            'chart' => app(EmployeeTurnoverReport::class)->chart($rows, $filters),
            'summary' => [
                ['label' => __('reports.columns.hires'), 'value' => Formatter::number((int) $rows->sum('hires'))],
                ['label' => __('reports.columns.terminations'), 'value' => Formatter::number($terminations)],
                ['label' => __('dashboard.labels.turnover_12_months'), 'value' => Formatter::percent($average > 0 ? $terminations * 100 / $average : 0)],
            ],
            'url' => $this->reportUrl($user, 'employee_turnover', [...$filters, 'from' => $from]),
        ];
    }

    /**
     * Employed staff by employment type and by gender — the employee_distribution report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function employeeDistribution(array $filters, User $user): array
    {
        $report = app(EmployeeDistributionReport::class);
        $byType = $report->query([...$this->employeeFilters($filters), 'group_by' => 'employment_type'], $user);
        $byGender = $report->query([...$this->employeeFilters($filters), 'group_by' => 'gender'], $user);

        return [
            'type' => 'chart',
            'chart' => ChartData::series('doughnut', __('reports.columns.employees'), $byType->pluck('total', 'group')->all()),
            'summary' => $byGender->map(fn (array $row): array => ['label' => $row['group'], 'value' => Formatter::number($row['total'])])->all(),
            'url' => $this->reportUrl($user, 'employee_distribution', [...$filters, 'group_by' => 'employment_type'], withRange: false),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function payrollPending(array $filters, User $user): array
    {
        return $this->stat(PayrollRun::query()->where('status', PayrollRunStatus::PendingApproval)->count(), null,
            route('admin.payroll-runs.index', ['status' => PayrollRunStatus::PendingApproval->value]));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function activeAdmins(array $filters, User $user): array
    {
        return $this->stat(User::query()->admins()->where('status', UserStatus::Active)->count(), null, route('admin.admin-users.index', ['status' => UserStatus::Active->value]));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function roles(array $filters, User $user): array
    {
        return $this->stat(Role::query()->count(), null, route('admin.roles.index'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function auditToday(array $filters, User $user): array
    {
        return $this->stat(AuditLog::query()->where('created_at', '>=', today())->count(), null, route('admin.audit-logs.index', ['date_from' => today()->toDateString()]));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function failedLogins(array $filters, User $user): array
    {
        return $this->stat(LoginHistory::query()->where('successful', false)->where('created_at', '>=', now()->subDay())->count(), __('app.dashboard.stats.last_24_hours'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function upcoming(array $filters, User $user, string $column, bool $anniversary): array
    {
        $today = CarbonImmutable::parse($this->today());
        $window = 30;
        $days = [];

        for ($offset = 0; $offset <= $window; $offset++) {
            $days[] = $today->addDays($offset)->format('m-d');
        }

        $employees = $this->employees($filters)
            ->where('employees.status', '!=', EmployeeStatus::Terminated->value)
            ->whereNotNull("employees.{$column}")
            ->whereIn(DB::raw("date_format(employees.{$column}, '%m-%d')"), $days)
            ->when($anniversary, fn (Builder $query) => $query->where("employees.{$column}", '<', $today->subYear()->addDays($window)->format('Y-m-d')))
            ->get(['employees.id', 'employees.employee_number', 'employees.name_ar', 'employees.name_en', "employees.{$column}"]);

        $items = $employees->map(function (Employee $employee) use ($column, $today): array {
            /** @var CarbonImmutable $date */
            $date = CarbonImmutable::instance($employee->{$column});
            $next = $date->setYear($today->year);

            if ($next->lt($today)) {
                $next = $next->addYear();
            }

            return ['employee' => $employee, 'next' => $next, 'years' => (int) $date->diffInYears($next)];
        })->filter(fn (array $item): bool => ! $anniversary || $item['years'] >= 1)->sortBy('next')->values();

        return [
            'type' => 'list',
            'count' => Formatter::number($items->count()),
            'hint' => __('dashboard.hints.next_days', ['days' => Formatter::number($window)]),
            'items' => $items->take(6)->map(fn (array $item): array => [
                'label' => $item['employee']->name,
                'meta' => $anniversary ? __('dashboard.labels.years_of_service', ['count' => Formatter::number((int) $item['years'])]) : Formatter::digits($item['employee']->employee_number),
                'value' => $item['next']->isSameDay($today) ? __('dashboard.labels.today') : Formatter::date($item['next']),
                'url' => $user->can('employees.view') ? route('admin.employees.show', $item['employee']->id) : null,
            ])->all(),
            'url' => null,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function placementChart(Collection $rows, ?string $url): array
    {
        return [
            'type' => 'chart',
            'chart' => ChartData::make('bar', $rows->pluck('code')->all(), [
                ['label' => __('reports.columns.active'), 'data' => $rows->pluck('active')->all(), 'color' => 'chart-1'],
                ['label' => __('reports.columns.inactive'), 'data' => $rows->pluck('inactive')->all(), 'color' => 'chart-6'],
                ['label' => __('reports.columns.hires_in_period'), 'data' => $rows->pluck('hires')->all(), 'color' => 'chart-2'],
                ['label' => __('reports.columns.terminations_in_period'), 'data' => $rows->pluck('terminations')->all(), 'color' => 'chart-5'],
            ]),
            'url' => $url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stat(int $value, ?string $hint = null, ?string $url = null): array
    {
        return ['type' => 'stat', 'value' => Formatter::number($value), 'raw' => $value, 'hint' => $hint, 'url' => $url];
    }

    /**
     * Filters → the employee filter keys of ReportFilters (branch, department, employee group).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function employeeFilters(array $filters): array
    {
        return array_filter([
            'branch_id' => $filters['branch_id'] ?? null,
            'department_id' => $filters['department_id'] ?? null,
            'group_id' => $filters['group_id'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Employee>
     */
    private function employees(array $filters): Builder
    {
        return ReportFilters::constrainEmployees(Employee::query(), $this->employeeFilters($filters), ['branch', 'department', 'employee_group']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceDay>
     */
    private function days(array $filters): Builder
    {
        $query = AttendanceDay::query()->whereBetween('attendance_days.work_date', [$filters['from'], $filters['to']]);

        return ReportFilters::applyEmployeeFilters($query, 'attendance_days.employee_id', $this->employeeFilters($filters),
            ['branch' => 'attendance_days.branch_id', 'department' => 'attendance_days.department_id']);
    }

    /**
     * Live pay items (regular slot of finalised runs) joined to their period, filtered by the item placement.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<PayrollItem>
     */
    private function liveItems(array $filters): Builder
    {
        $query = PayrollItem::query()
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_items.payroll_run_id')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_items.payroll_period_id')
            ->where('payroll_runs.status', PayrollRunStatus::Finalised->value)
            ->where('payroll_items.pay_slot', 'regular');

        return ReportFilters::applyEmployeeFilters($query, 'payroll_items.employee_id', $this->employeeFilters($filters),
            ['branch' => 'payroll_items.branch_id', 'department' => 'payroll_items.department_id']);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    private function vacancyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['branch_id'] ?? null, fn (Builder $query, $id) => $query->where('vacancies.branch_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, $id) => $query->whereIn('vacancies.department_id', $this->scopes->withSubDepartments([(int) $id])));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int>
     */
    private function todayCounts(array $filters): array
    {
        $key = json_encode($this->employeeFilters($filters));

        return $this->todayCounts[$key] ??= $this->attendance->counts(['date' => $this->today(), ...$this->employeeFilters($filters)]);
    }

    private function today(): string
    {
        return BulkAttendance::today()->format('Y-m-d');
    }

    /**
     * Drill-down URL into a report of the reports centre, carrying the dashboard filters — only
     * when the admin may open that report.
     *
     * @param  array<string, mixed>  $filters
     */
    private function reportUrl(User $user, string $key, array $filters, bool $withRange = true): ?string
    {
        $report = $this->reports->find($key);

        if ($report === null || ! $report->isAvailableTo($user)) {
            return null;
        }

        $params = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        if (! $withRange || ! in_array('date_range', $report->filters(), true)) {
            unset($params['from'], $params['to']);
        }

        return route('admin.reports.show', [$key, ...$params]);
    }
}
