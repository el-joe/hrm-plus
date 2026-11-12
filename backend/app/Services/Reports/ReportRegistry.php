<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Reports\Definitions\Attendance;
use App\Services\Reports\Definitions\Employees;
use App\Services\Reports\Definitions\Leave;
use App\Services\Reports\Definitions\Payroll;
use App\Services\Reports\Definitions\Performance;
use App\Services\Reports\Definitions\Recruitment;
use App\Services\Reports\Definitions\Services;
use Illuminate\Support\Collection;

/**
 * Every report of the reports centre, in catalogue order. The 48 reports of the spec
 * (module 16 "Required Reports") are listed in REQUIRED by key; tests assert each is registered.
 * Three more cover the modules the brief adds (HR requests, assets, disciplinary) plus the Phase 5b
 * manual-entries report.
 */
class ReportRegistry
{
    /**
     * Areas in display order.
     */
    public const AREAS = ['employees', 'attendance', 'leave', 'payroll', 'recruitment', 'performance', 'requests', 'assets', 'disciplinary'];

    /**
     * Module view permissions that open each report area (any one of them), besides reports.view.
     * reports.manage opens every area (Phase 11: a Recruiter sees recruitment reports only).
     *
     * @var array<string, list<string>>
     */
    public const AREA_PERMISSIONS = [
        'employees' => ['employees.view'],
        'attendance' => ['attendance.view'],
        'leave' => ['leaves.view'],
        'payroll' => ['payroll.view', 'payslips.view', 'loans.view', 'allowances.view', 'deductions.view'],
        'recruitment' => ['recruitment.view'],
        'performance' => ['performance.view'],
        'requests' => ['hr_requests.view'],
        'assets' => ['assets.view'],
        'disciplinary' => ['disciplinary.view'],
    ];

    /**
     * Spec module 16 "Required Reports" → report key.
     *
     * @var array<string, list<string>>
     */
    public const REQUIRED = [
        'employees' => ['employee_directory', 'employee_distribution', 'new_hires', 'terminated_employees', 'employee_turnover', 'employee_history', 'contract_expiration', 'employees_by_branch', 'employees_by_department'],
        'attendance' => ['daily_attendance', 'monthly_attendance', 'absence', 'late_arrivals', 'early_departures', 'overtime', 'missing_punches', 'attendance_source', 'device_synchronization', 'attendance_corrections'],
        'leave' => ['leave_balance', 'leave_requests', 'approved_leaves', 'rejected_leaves', 'leave_utilization', 'employees_on_leave'],
        'payroll' => ['payroll_summary', 'payroll_by_branch', 'payroll_by_department', 'gross_salary', 'net_salary', 'allowances', 'deductions', 'overtime_payments', 'loan_balances', 'advances', 'payroll_adjustments', 'payroll_history'],
        'recruitment' => ['vacancies', 'candidate_pipeline', 'applications', 'interview_results', 'hiring_sources', 'recruitment_conversion', 'time_to_hire'],
        'performance' => ['performance_scores', 'goals', 'completed_reviews', 'training_progress'],
    ];

    /**
     * @var list<class-string<Report>>
     */
    private const REPORTS = [
        Employees\EmployeeDirectoryReport::class, Employees\EmployeeDistributionReport::class, Employees\NewHiresReport::class,
        Employees\TerminatedEmployeesReport::class, Employees\EmployeeTurnoverReport::class, Employees\EmployeeHistoryReport::class,
        Employees\ContractExpirationReport::class, Employees\EmployeesByBranchReport::class, Employees\EmployeesByDepartmentReport::class,

        Attendance\DailyAttendanceReport::class, Attendance\MonthlyAttendanceReport::class, Attendance\AbsenceReport::class,
        Attendance\LateArrivalsReport::class, Attendance\EarlyDeparturesReport::class, Attendance\OvertimeReport::class,
        Attendance\MissingPunchesReport::class, Attendance\AttendanceSourceReport::class, Attendance\DeviceSynchronizationReport::class,
        Attendance\AttendanceCorrectionsReport::class, Attendance\ManualEntriesReport::class,

        Leave\LeaveBalanceReport::class, Leave\LeaveRequestsReport::class, Leave\ApprovedLeavesReport::class,
        Leave\RejectedLeavesReport::class, Leave\LeaveUtilizationReport::class, Leave\EmployeesOnLeaveReport::class,

        Payroll\PayrollSummaryReport::class, Payroll\PayrollByBranchReport::class, Payroll\PayrollByDepartmentReport::class,
        Payroll\GrossSalaryReport::class, Payroll\NetSalaryReport::class, Payroll\AllowancesReport::class,
        Payroll\DeductionsReport::class, Payroll\OvertimePaymentsReport::class, Payroll\LoanBalancesReport::class,
        Payroll\AdvancesReport::class, Payroll\PayrollAdjustmentsReport::class, Payroll\PayrollHistoryReport::class,

        Recruitment\VacanciesReport::class, Recruitment\CandidatePipelineReport::class, Recruitment\ApplicationsReport::class,
        Recruitment\InterviewResultsReport::class, Recruitment\HiringSourcesReport::class, Recruitment\RecruitmentConversionReport::class,
        Recruitment\TimeToHireReport::class,

        Performance\PerformanceScoresReport::class, Performance\GoalsReport::class, Performance\CompletedReviewsReport::class,
        Performance\TrainingProgressReport::class,

        Services\HrRequestsReport::class, Services\AssetRegisterReport::class, Services\DisciplinaryCasesReport::class,
    ];

    /** @var array<string, Report>|null */
    private ?array $reports = null;

    /**
     * @return array<string, Report>
     */
    public function all(): array
    {
        if ($this->reports === null) {
            $this->reports = [];

            foreach (self::REPORTS as $class) {
                $report = app($class);
                $this->reports[$report->key()] = $report;
            }
        }

        return $this->reports;
    }

    public function find(string $key): ?Report
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Reports the admin may open, grouped by area in display order.
     *
     * @return Collection<string, list<Report>>
     */
    public function catalogue(User $user): Collection
    {
        $available = collect($this->all())->filter(fn (Report $report): bool => $report->isAvailableTo($user));

        return collect(self::AREAS)
            ->mapWithKeys(fn (string $area): array => [$area => $available->filter(fn (Report $report): bool => $report->area() === $area)->values()->all()])
            ->filter(fn (array $reports): bool => $reports !== []);
    }

    public function isRequired(string $key): bool
    {
        return in_array($key, array_merge(...array_values(self::REQUIRED)), true);
    }
}
