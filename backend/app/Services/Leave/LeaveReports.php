<?php

namespace App\Services\Leave;

use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Attendance\BulkAttendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Leave reports (spec module 15 "Leave Reports"): each report is defined
 * once here — its columns, query and row — and rendered generically by
 * admin/leave/reports/show and the Excel / CSV export. Queries start from
 * models with the admin data scope, so the screen and the export see the
 * same employees.
 *
 * balances, requests (approved / rejected through the status filter),
 * usage_by_type, usage_by_department (utilisation), upcoming, on_leave.
 */
class LeaveReports
{
    public const REPORTS = ['balances', 'requests', 'usage_by_type', 'usage_by_department', 'upcoming', 'on_leave'];

    /** @var Collection<int, LeaveType>|null */
    private ?Collection $types = null;

    /**
     * Balance summaries loaded by primeBalances(), keyed "year" => employee id => type id => figures.
     *
     * @var array<int, array<int, array<int, array{balance: string, pending: string, available: string, used: string}>>>
     */
    private array $primedBalances = [];

    public function __construct(private readonly LeaveBalanceCalculator $balances, private readonly LeaveYear $years) {}

    /**
     * Default filters of a report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function withDefaults(string $report, array $filters): array
    {
        $today = BulkAttendance::today();
        $year = (int) ($filters['year'] ?? $this->years->of($today));

        return match ($report) {
            'balances' => ['year' => $year, ...$filters],
            'upcoming' => ['from' => $today->format('Y-m-d'), 'to' => $today->addDays(30)->format('Y-m-d'), ...$filters],
            'on_leave' => ['date' => $today->format('Y-m-d'), ...$filters],
            default => ['from' => $this->years->start($year)->format('Y-m-d'), 'to' => $this->years->end($year)->format('Y-m-d'), ...$filters],
        };
    }

    /**
     * @return array<string, string> key => translated label
     */
    public function columns(string $report): array
    {
        $label = fn (string $key): string => __('leave.reports.columns.'.$key);

        return match ($report) {
            'balances' => [
                'employee_number' => $label('employee_number'), 'employee' => $label('employee'), 'department' => $label('department'),
                ...$this->balanceTypes()->mapWithKeys(fn (LeaveType $type): array => [
                    'type_'.$type->id => $type->name.' — '.$label('available'),
                ])->all(),
            ],
            'requests' => [
                'id' => '#', 'employee_number' => $label('employee_number'), 'employee' => $label('employee'), 'department' => $label('department'),
                'type' => $label('type'), 'start_date' => $label('start_date'), 'end_date' => $label('end_date'), 'days' => $label('days'),
                'status' => $label('status'), 'requested_at' => $label('requested_at'), 'decided_at' => $label('decided_at'), 'note' => $label('note'),
            ],
            'usage_by_type' => [
                'type' => $label('type'), 'paid' => $label('paid'), 'requests' => $label('requests'), 'employees' => $label('employees'), 'days' => $label('days'),
            ],
            'usage_by_department' => [
                'department' => $label('department'), 'type' => $label('type'), 'requests' => $label('requests'), 'employees' => $label('employees'), 'days' => $label('days'),
            ],
            'upcoming', 'on_leave' => [
                'employee_number' => $label('employee_number'), 'employee' => $label('employee'), 'department' => $label('department'),
                'type' => $label('type'), 'start_date' => $label('start_date'), 'end_date' => $label('end_date'), 'days' => $label('days'), 'status' => $label('status'),
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function numeric(string $report): array
    {
        return match ($report) {
            'balances' => $this->balanceTypes()->map(fn (LeaveType $type): string => 'type_'.$type->id)->all(),
            'usage_by_type', 'usage_by_department' => ['requests', 'employees', 'days'],
            default => ['days'],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Model>
     */
    public function query(string $report, array $filters): Builder
    {
        return match ($report) {
            'balances' => $this->employees($filters)->with('department')->orderBy('employee_number'),
            'requests' => $this->requests($filters)
                ->overlapping($filters['from'], $filters['to'])
                ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
                ->orderBy('start_date')->orderBy('id'),
            'usage_by_type' => $this->usage($filters)
                ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
                ->selectRaw('leave_types.id as type_id, leave_types.is_paid, COUNT(DISTINCT leave_requests.id) as requests, COUNT(DISTINCT employees.id) as employees, SUM(leave_request_days.amount) as days')
                ->groupBy('leave_types.id', 'leave_types.is_paid', 'leave_types.sort_order')->orderBy('leave_types.sort_order'),
            'usage_by_department' => $this->usage($filters)
                ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
                ->selectRaw('departments.code as department_code, departments.name_ar as department_ar, departments.name_en as department_en, leave_requests.leave_type_id as type_id, COUNT(DISTINCT leave_requests.id) as requests, COUNT(DISTINCT employees.id) as employees, SUM(leave_request_days.amount) as days')
                ->groupBy('departments.code', 'departments.name_ar', 'departments.name_en', 'leave_requests.leave_type_id')
                ->orderBy('departments.code')->orderBy('leave_requests.leave_type_id'),
            'upcoming' => $this->requests($filters)->inStatus([LeaveRequestStatus::Approved, LeaveRequestStatus::Pending])
                ->whereBetween('start_date', [$filters['from'], $filters['to']])->orderBy('start_date'),
            'on_leave' => $this->requests($filters)->inStatus(LeaveRequestStatus::taken())
                ->overlapping($filters['date'], $filters['date'])->orderBy('end_date'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string|int|null>
     */
    public function row(string $report, Model $record, array $filters): array
    {
        return match ($report) {
            'balances' => $this->balanceRow($record, (int) $filters['year']),
            'requests', 'upcoming', 'on_leave' => $this->requestRow($report, $record),
            'usage_by_type' => [
                'type' => $this->type((int) $record->type_id)?->name,
                'paid' => $record->is_paid ? __('leave.common.yes') : __('leave.common.no'),
                'requests' => (int) $record->requests,
                'employees' => (int) $record->employees,
                'days' => bcadd((string) $record->days, '0', 2),
            ],
            'usage_by_department' => [
                'department' => $record->department_code ? $record->department_code.' · '.(app()->getLocale() === 'en' ? $record->department_en : $record->department_ar) : '—',
                'type' => $this->type((int) $record->type_id)?->name,
                'requests' => (int) $record->requests,
                'employees' => (int) $record->employees,
                'days' => bcadd((string) $record->days, '0', 2),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Employee>
     */
    private function employees(array $filters): Builder
    {
        return Employee::query()->employed()
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $id) => $query->where('employees.branch_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('employees.department_id', $id))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<LeaveRequest>
     */
    private function requests(array $filters): Builder
    {
        return LeaveRequest::query()->with(['employee.department', 'leaveType'])
            ->when($filters['leave_type_id'] ?? null, fn (Builder $query, int $id) => $query->where('leave_type_id', $id))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('employee', fn (Builder $query) => $query->where('branch_id', $id)))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->whereHas('employee', fn (Builder $query) => $query->where('department_id', $id)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('employee', fn (Builder $query) => $query->search($search)));
    }

    /**
     * Counted days of taken leave dated inside the range, joined to the (scoped) employees.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Employee>
     */
    private function usage(array $filters): Builder
    {
        return Employee::query()
            ->join('leave_request_days', 'leave_request_days.employee_id', '=', 'employees.id')
            ->join('leave_requests', 'leave_requests.id', '=', 'leave_request_days.leave_request_id')
            ->where('leave_request_days.is_counted', true)
            ->whereBetween('leave_request_days.date', [$filters['from'], $filters['to']])
            ->whereIn('leave_requests.status', array_map(fn (LeaveRequestStatus $status): string => $status->value, LeaveRequestStatus::taken()))
            ->when($filters['leave_type_id'] ?? null, fn (Builder $query, int $id) => $query->where('leave_requests.leave_type_id', $id))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $id) => $query->where('employees.branch_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('employees.department_id', $id));
    }

    /**
     * @return array<string, string|int|null>
     */
    /**
     * Load the balances of a page / chunk of employees in one go (the balances report).
     *
     * @param  list<int>  $employeeIds
     */
    public function primeBalances(array $employeeIds, int $year): void
    {
        $this->primedBalances[$year] = $this->balances->summary($employeeIds, $this->balanceTypes()->modelKeys(), $year) + ($this->primedBalances[$year] ?? []);
    }

    private function balanceRow(Employee $employee, int $year): array
    {
        $summary = $this->primedBalances[$year][$employee->id]
            ?? $this->balances->summary([$employee->id], $this->balanceTypes()->modelKeys(), $year)[$employee->id] ?? [];

        return [
            'employee_number' => $employee->employee_number,
            'employee' => $employee->name,
            'department' => $employee->department?->name,
            ...$this->balanceTypes()->mapWithKeys(fn (LeaveType $type): array => ['type_'.$type->id => $summary[$type->id]['available'] ?? '0.00'])->all(),
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    private function requestRow(string $report, LeaveRequest $request): array
    {
        $row = [
            'employee_number' => $request->employee?->employee_number,
            'employee' => $request->employee?->name,
            'department' => $request->employee?->department?->name,
            'type' => $request->leaveType->name,
            'start_date' => $request->start_date->format('Y-m-d'),
            'end_date' => $request->end_date->format('Y-m-d'),
            'days' => bcadd((string) $request->days, '0', 2),
            'status' => $request->status->label(),
        ];

        if ($report !== 'requests') {
            return $row;
        }

        return [
            'id' => $request->id,
            ...$row,
            'requested_at' => $request->created_at?->format('Y-m-d H:i'),
            'decided_at' => $request->decided_at?->format('Y-m-d H:i'),
            'note' => $request->rejection_reason ?? $request->cancellation_reason ?? $request->reason,
        ];
    }

    /**
     * @return Collection<int, LeaveType>
     */
    private function balanceTypes(): Collection
    {
        return $this->allTypes()->filter(fn (LeaveType $type): bool => $type->tracks_balance && $type->is_active)->values();
    }

    private function type(int $id): ?LeaveType
    {
        return $this->allTypes()->firstWhere('id', $id);
    }

    /**
     * @return Collection<int, LeaveType>
     */
    private function allTypes(): Collection
    {
        return $this->types ??= LeaveType::query()->ordered()->get();
    }
}
