<?php

namespace App\Services\Employees;

use App\Enums\SalaryStructureScope;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeContract;
use App\Models\EmployeeLoan;
use App\Models\EmployeeSalary;
use App\Models\LeaveRequest;
use App\Models\PayEntry;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureAssignment;
use App\Models\User;
use App\Services\Assets\AssetTimeline;
use App\Services\Assets\EmployeeAssetsTab;
use App\Services\Disciplinary\DisciplinaryTimeline;
use App\Services\Disciplinary\EmployeeDisciplinaryTab;
use App\Services\Employees\Contracts\ProfileTab;
use App\Services\Employees\Contracts\TimelineSource;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeaveYear;
use App\Services\Payroll\PayEntryResolver;
use App\Services\Payroll\SalaryStructureResolver;
use App\Services\Performance\EmployeePerformanceTab;
use App\Services\Performance\PerformanceTimeline;
use App\Services\Training\EmployeeTrainingTab;
use App\Services\Training\TrainingTimeline;
use App\Support\SensitiveValue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data of the admin employee profile, one tab at a time (link tabs: each tab
 * is its own request, so viewing protected data is logged precisely):
 *
 * - personal: identity numbers are masked; an admin with
 *   employee_sensitive.view may reveal them (?reveal=1), which is logged;
 * - financial (employee_financials.view): salary, salary history and bank
 *   accounts; opening the tab is logged;
 * - history: the placement timeline, plus salary rows for financial viewers
 *   (logged as financial access as well).
 */
class EmployeeProfile
{
    /**
     * Module tabs (Phase 8a), in display order before "history".
     *
     * @var array<string, class-string<ProfileTab>>
     */
    public const MODULE_TABS = [
        'performance' => EmployeePerformanceTab::class,
        'training' => EmployeeTrainingTab::class,
        'assets' => EmployeeAssetsTab::class,
        'disciplinary' => EmployeeDisciplinaryTab::class,
    ];

    /**
     * Module events merged into the history timeline (Phase 8a).
     *
     * @var list<class-string<TimelineSource>>
     */
    public const TIMELINE_SOURCES = [
        PerformanceTimeline::class,
        TrainingTimeline::class,
        AssetTimeline::class,
        DisciplinaryTimeline::class,
    ];

    public function __construct(
        private readonly SensitiveDataAccessLog $accessLog,
        private readonly ManagerResolver $managers,
        private readonly CustomFieldValues $customFields,
    ) {}

    /**
     * @return list<array{label: string, url: string, active: bool}>
     */
    public function tabs(User $user, Employee $employee, string $active): array
    {
        $tabs = ['personal', 'employment', 'contracts'];

        if ($user->can('viewDocuments', $employee)) {
            $tabs[] = 'documents';
        }

        if ($user->can('viewFinancial', $employee)) {
            $tabs[] = 'financial';
            $tabs[] = 'compensation';
        }

        if ($user->can('leaves.view') || $user->can('leave_balances.view')) {
            $tabs[] = 'leave';
        }

        foreach (self::MODULE_TABS as $tab => $class) {
            if (app($class)->visibleTo($user, $employee)) {
                $tabs[] = $tab;
            }
        }

        $tabs[] = 'history';

        return array_map(fn (string $tab): array => [
            'label' => __('employees.tabs.'.$tab),
            'url' => $tab === 'personal' ? route('admin.employees.show', $employee) : route('admin.employees.tab', [$employee, $tab]),
            'active' => $tab === $active,
        ], $tabs);
    }

    /**
     * @return array<string, mixed>
     */
    public function tabData(User $user, Employee $employee, string $tab, bool $reveal = false): array
    {
        $employee->load(['branch', 'department', 'jobTitle', 'employmentType', 'manager', 'user']);

        return match ($tab) {
            'personal' => $this->personal($user, $employee, $reveal),
            'employment' => $this->employment($employee),
            'contracts' => ['contracts' => $employee->contracts()->with('renewalOf')->orderByDesc('start_date')->get()],
            'documents' => ['documents' => $employee->documents()->with('uploader')->latest('id')->get()],
            'financial' => $this->financial($user, $employee),
            'compensation' => $this->compensation($user, $employee),
            'leave' => $this->leave($employee),
            'history' => $this->history($user, $employee),
            default => app(self::MODULE_TABS[$tab])->data($user, $employee),
        };
    }

    /**
     * False only for a module tab the admin may not open (other tabs are checked by the controller).
     */
    public function moduleTabVisible(User $user, Employee $employee, string $tab): bool
    {
        return ! isset(self::MODULE_TABS[$tab]) || app(self::MODULE_TABS[$tab])->visibleTo($user, $employee);
    }

    /**
     * @return array<string, mixed>
     */
    private function personal(User $user, Employee $employee, bool $reveal): array
    {
        $canView = $user->can('viewSensitive', $employee);
        $revealed = $reveal && $canView;
        $identity = [
            'national_id' => $employee->national_id,
            'passport_number' => $employee->passport_number,
        ];

        if ($revealed) {
            $this->accessLog->record(SensitiveDataAccessLog::IDENTITY, $employee, array_keys(array_filter($identity)), viewer: $user);
        }

        return [
            'identity' => array_map(fn (?string $value): ?string => $revealed ? $value : SensitiveValue::mask($value), $identity),
            'identityRevealed' => $revealed,
            'canRevealIdentity' => $canView && array_filter($identity) !== [],
            'contacts' => $employee->emergencyContacts()->get(),
            'groups' => $employee->groups()->orderBy('code')->get(),
            'customFields' => $this->customFields->display($employee),
            'account' => $employee->user,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function employment(Employee $employee): array
    {
        $employee->load(['team', 'position', 'jobGrade', 'costCenter', 'workLocation', 'contracts']);
        $department = $employee->department()->withoutGlobalScopes()->first();
        $branch = $employee->branch()->withoutGlobalScopes()->first();

        return [
            'departmentHead' => $department ? $this->managers->departmentHead($department) : null,
            'branchManager' => $branch ? $this->managers->branchManager($branch) : null,
            'directReports' => $employee->directReports()->employed()->orderBy('employee_number')->get(),
            'currentContract' => $employee->currentContract(),
            'pending' => $employee->assignments()->pending()->with(['department', 'position', 'manager'])->orderBy('effective_date')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function financial(User $user, Employee $employee): array
    {
        $this->accessLog->record(SensitiveDataAccessLog::FINANCIAL, $employee, ['basic_salary', 'salary_history', 'bank_accounts'], viewer: $user);

        return [
            'salaries' => $employee->salaries()->with('creator')->orderByDesc('effective_date')->orderByDesc('id')->get(),
            'bankAccounts' => $employee->bankAccounts()->orderByDesc('is_primary')->orderBy('id')->get(),
            'currency' => (string) setting('localisation.currency_code'),
        ];
    }

    /**
     * Compensation (Phase 7a, employee_financials.view; opening it is logged): the salary
     * structure in force and its history, the allowances / deductions that apply this month
     * (after scope precedence) and those addressed to the employee, and loans.
     *
     * @return array<string, mixed>
     */
    private function compensation(User $user, Employee $employee): array
    {
        $this->accessLog->record(SensitiveDataAccessLog::FINANCIAL, $employee, ['basic_salary', 'salary_structure', 'allowances', 'deductions', 'loans'], viewer: $user);

        $today = CarbonImmutable::now();
        $structures = app(SalaryStructureResolver::class);

        return [
            'structure' => $structures->for($employee, $today),
            'structureAssignment' => $structures->assignmentFor($employee, $today->toDateString()),
            'employeeAssignments' => SalaryStructureAssignment::query()->where('scope_type', SalaryStructureScope::Employee)->where('scope_id', $employee->id)
                ->with(['structure', 'creator'])->orderByDesc('effective_from')->get(),
            'applying' => app(PayEntryResolver::class)->for($employee, $today->startOfMonth(), $today->endOfMonth()),
            'entries' => PayEntry::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->with('component')->latest('id')->limit(30)->get(),
            'loans' => EmployeeLoan::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->latest('id')->get(),
            'structuresList' => SalaryStructure::query()->active()->orderBy('code')->get(),
            'currency' => (string) setting('localisation.currency_code'),
            'month' => $today->startOfMonth(),
        ];
    }

    /**
     * Leave balances of the current leave year and the latest requests (Phase 6).
     *
     * @return array<string, mixed>
     */
    private function leave(Employee $employee): array
    {
        $years = app(LeaveYear::class);

        return [
            'leaveYear' => $years->label($years->current()),
            'balances' => app(LeaveBalanceCalculator::class)->all($employee),
            'leaveRequests' => LeaveRequest::query()->where('employee_id', $employee->id)->with('leaveType')->latest('start_date')->limit(20)->get(),
        ];
    }

    /**
     * Timeline entries, newest first: placement rows, contracts and (for
     * financial viewers) salary rows.
     *
     * @return array<string, mixed>
     */
    private function history(User $user, Employee $employee): array
    {
        $withSalary = $user->can('viewFinancial', $employee);

        /** @var Collection<int, array{date: Carbon, kind: string, record: mixed}> $entries */
        $entries = $employee->assignments()
            ->with(['branch', 'department', 'team', 'position', 'jobTitle', 'jobGrade', 'employmentType', 'manager', 'creator'])
            ->get()
            ->map(fn (EmployeeAssignment $row): array => ['date' => $row->effective_date, 'kind' => 'assignment', 'record' => $row, 'id' => $row->id]);

        $entries = $entries->merge($employee->contracts()->get()->map(fn (EmployeeContract $contract): array => [
            'date' => $contract->start_date, 'kind' => 'contract', 'record' => $contract, 'id' => $contract->id,
        ]));

        if ($withSalary) {
            $this->accessLog->record(SensitiveDataAccessLog::FINANCIAL, $employee, ['salary_history'], viewer: $user);
            $entries = $entries->merge($employee->salaries()->with('creator')->get()->map(fn (EmployeeSalary $salary): array => [
                'date' => $salary->effective_date, 'kind' => 'salary', 'record' => $salary, 'id' => $salary->id,
            ]));
        }

        foreach (self::TIMELINE_SOURCES as $source) {
            $entries = $entries->toBase()->merge(app($source)->entries($user, $employee)->toBase()->all());
        }

        return [
            'entries' => $entries->sortBy([['date', 'desc'], ['id', 'desc']])->values(),
            'previous' => $this->previousSnapshots($employee),
            'currency' => (string) setting('localisation.currency_code'),
        ];
    }

    /**
     * For each assignment row, the row before it (to show "from → to").
     *
     * @return array<int, EmployeeAssignment|null>
     */
    private function previousSnapshots(Employee $employee): array
    {
        $rows = $employee->assignments()->whereNull('cancelled_at')
            ->with(['branch', 'department', 'position', 'jobTitle', 'jobGrade', 'manager'])
            ->orderBy('effective_date')->orderBy('id')->get();
        $previous = [];
        $last = null;

        foreach ($rows as $row) {
            $previous[$row->id] = $last;
            $last = $row;
        }

        return $previous;
    }
}
