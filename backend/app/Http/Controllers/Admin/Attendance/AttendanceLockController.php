<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Actions\Attendance\ManagePeriodLock;
use App\Enums\EmployeeStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendancePeriodLock;
use App\Models\Branch;
use App\Models\Employee;
use App\Rules\ExistsInScope;
use App\Services\Attendance\AttendanceRecalculation;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Period locks (attendance.manage) and reprocessing of a period, e.g. after a
 * schedule or policy assignment was changed retroactively.
 */
class AttendanceLockController extends Controller
{
    public function index(): View
    {
        return view('admin.attendance.locks', [
            'locks' => AttendancePeriodLock::query()->with(['branch', 'locker'])->withCount('days')->latest('start_date')->latest('id')->paginate(20),
            'branches' => Branch::query()->active()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, ManagePeriodLock $locks): RedirectResponse
    {
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before:today'],
            'branch_id' => ['nullable', 'integer', new ExistsInScope(Branch::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $locks->lock($data, $request->user());

        return back()->with('status', __('attendance.locks.locked'));
    }

    public function unlock(Request $request, AttendancePeriodLock $lock, ManagePeriodLock $locks): RedirectResponse
    {
        $locks->unlock($lock, $request->user());

        return back()->with('status', __('attendance.locks.unlocked'));
    }

    public function reprocess(Request $request, AttendanceRecalculation $recalculation, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from', 'before_or_equal:today'],
            'branch_id' => ['nullable', 'integer', new ExistsInScope(Branch::class)],
        ]);

        $dates = [];

        for ($date = CarbonImmutable::parse($data['from']); $date->lte(CarbonImmutable::parse($data['to'])) && count($dates) < 62; $date = $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }

        $count = 0;
        Employee::query()
            ->where(fn ($query) => $query->where('status', '!=', EmployeeStatus::Terminated)->orWhere('termination_date', '>=', $data['from']))
            ->when($data['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->each(function (Employee $employee) use ($recalculation, $dates, &$count): void {
                $recalculation->queue($employee->id, $dates);
                $count++;
            });

        $audit->log('attendance.reprocess_requested', meta: [...$data, 'employees' => $count]);

        return back()->with('status', __('attendance.locks.reprocess_queued', ['count' => $count]));
    }
}
