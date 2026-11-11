<?php

namespace App\Http\Controllers\Admin\Leave;

use App\Actions\Leave\AdjustLeaveBalance;
use App\Enums\LeaveTransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Leave\BalanceTransactionRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeaveYear;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Leave balances: employees × balance-tracked types for a leave year, the
 * breakdown of one employee (ledger rows, requests) and manual adjustments /
 * encashments. The export lives in the leave reports ("balances").
 */
class LeaveBalanceController extends Controller
{
    public function index(Request $request, LeaveBalanceCalculator $balances, LeaveYear $years): View
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'search' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);
        $year = (int) ($filters['year'] ?? $years->current());
        $types = LeaveType::query()->active()->where('tracks_balance', true)->ordered()->get();

        $employees = Employee::query()->employed()->with(['department'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search))
            ->when($filters['branch_id'] ?? null, fn (Builder $query, int $id) => $query->where('branch_id', $id))
            ->when($filters['department_id'] ?? null, fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->orderBy('employee_number')
            ->paginate(25)->withQueryString();

        return view('admin.leave.balances.index', [
            'employees' => $employees,
            'types' => $types,
            'summary' => $balances->summary($employees->getCollection()->modelKeys(), $types->modelKeys(), $year),
            'filters' => $filters,
            'year' => $year,
            'yearLabel' => $years->label($year),
            'branches' => Branch::query()->orderBy('code')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
        ]);
    }

    public function show(Request $request, Employee $employee, LeaveBalanceCalculator $balances, LeaveYear $years): View
    {
        Gate::authorize('view', $employee);

        $year = (int) ($request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']])['year'] ?? $years->current());

        return view('admin.leave.balances.show', [
            'employee' => $employee->load(['department', 'branch', 'employmentType']),
            'year' => $year,
            'yearLabel' => $years->label($year),
            'balances' => $balances->all($employee, $year),
            'transactions' => LeaveBalanceTransaction::query()->where('employee_id', $employee->id)->where('leave_year', $year)
                ->with(['leaveType', 'creator', 'leaveRequest'])->orderBy('effective_date')->orderBy('id')->get(),
            'requests' => LeaveRequest::query()->where('employee_id', $employee->id)->with('leaveType')->latest('start_date')->limit(50)->get(),
            'manualTypes' => LeaveTransactionType::manual(),
            'balanceTypes' => LeaveType::query()->where('tracks_balance', true)->ordered()->get(),
        ]);
    }

    public function store(BalanceTransactionRequest $request, Employee $employee, AdjustLeaveBalance $adjust): RedirectResponse
    {
        Gate::authorize('view', $employee);

        $row = $adjust->handle($employee, $request->validated(), $request->user());

        return redirect()->route('admin.leave-balances.show', [$employee, 'year' => $row->leave_year])
            ->with('status', __('leave.balances.recorded', ['type' => $row->type->label()]));
    }

    public function export(Request $request): RedirectResponse
    {
        return redirect()->route('admin.leave-reports.export', ['balances', ...$request->only(['year', 'branch_id', 'department_id', 'search']), 'format' => $request->query('format', 'xlsx')]);
    }
}
