<?php

namespace App\Services\Employees;

use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeContract;
use App\Models\EmployeeSalary;
use App\Models\User;
use App\Support\SensitiveValue;
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
            'history' => $this->history($user, $employee),
        };
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
