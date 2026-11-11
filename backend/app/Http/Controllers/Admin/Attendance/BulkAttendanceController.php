<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceManualBatch;
use App\Models\Branch;
use App\Models\Department;
use App\Models\EmployeeGroup;
use App\Models\Shift;
use App\Models\Team;
use App\Services\Attendance\BulkAttendance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "Mark all present except..." — step 1 (filters) and step 2 (employee list
 * with exceptions). Submitting creates a draft batch and leads to the
 * confirmation screen (AttendanceBatchController::show).
 */
class BulkAttendanceController extends Controller
{
    public function create(Request $request, BulkAttendance $bulk): View
    {
        Gate::authorize('create', AttendanceManualBatch::class);

        $filters = $this->filters($request, required: false);
        $loaded = isset($filters['date']);

        return view('admin.attendance.bulk', [
            'filters' => $filters,
            'rows' => $loaded ? $bulk->eligible($filters) : [],
            'loaded' => $loaded,
            'actions' => $bulk->actionOptions(),
            'branches' => Branch::query()->active()->orderBy('code')->get(),
            'departments' => Department::query()->active()->orderBy('code')->get(),
            'teams' => Team::query()->active()->orderBy('code')->get(),
            'shifts' => Shift::query()->active()->orderBy('code')->get(),
            'groups' => EmployeeGroup::query()->active()->orderBy('code')->get(),
            'today' => BulkAttendance::today()->format('Y-m-d'),
        ]);
    }

    public function store(Request $request, BulkAttendance $bulk): RedirectResponse
    {
        Gate::authorize('create', AttendanceManualBatch::class);

        $filters = $this->filters($request, required: true);
        $data = $request->validate([
            'employees' => ['required', 'array'],
            'employees.*.selected' => ['nullable', 'boolean'],
            'employees.*.action' => ['nullable', 'string', 'max:30'],
            'employees.*.check_in' => ['nullable', 'string', 'max:5'],
            'employees.*.check_out' => ['nullable', 'string', 'max:5'],
            'employees.*.note' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $batch = $bulk->createDraft($filters, $data['employees'], $data['note'] ?? null, $request->user());

        return redirect()->route('admin.attendance.batches.show', $batch);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request, bool $required): array
    {
        return array_filter($request->validate([
            'date' => [$required ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:+7 days'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'team_id' => ['nullable', 'integer'],
            'shift_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
        ]), fn ($value): bool => filled($value));
    }
}
