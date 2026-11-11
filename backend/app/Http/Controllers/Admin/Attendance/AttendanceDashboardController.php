<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Services\Attendance\BulkAttendance;
use App\Services\Attendance\Reports\AttendanceDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The attendance dashboard (attendance.view): today's (or a chosen date's)
 * expected / present / late / absent / on leave / not yet in, by branch and
 * department; latest punches; device health; pending reviews.
 */
class AttendanceDashboardController extends Controller
{
    public function __invoke(Request $request, AttendanceDashboard $dashboard): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
        ]);
        $filters['date'] ??= BulkAttendance::today()->format('Y-m-d');

        return view('admin.attendance.dashboard', [
            'filters' => $filters,
            'data' => $dashboard->build($request->user(), $filters),
            'branches' => Branch::query()->orderBy('code')->get(),
            'departments' => Department::query()->orderBy('code')->get(),
        ]);
    }
}
