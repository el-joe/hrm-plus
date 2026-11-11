<?php

namespace App\Http\Controllers\Admin\Scheduling;

use App\Actions\Structure\DeleteStructureRecord;
use App\Enums\AttendanceStatusCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Scheduling\AttendanceStatusRequest;
use App\Models\AttendanceStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Configurable day statuses offered by manual / bulk attendance.
 */
class AttendanceStatusController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', AttendanceStatus::class);

        return view('admin.scheduling.statuses.index', [
            'records' => AttendanceStatus::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', AttendanceStatus::class);

        return $this->form(new AttendanceStatus([
            'category' => AttendanceStatusCategory::Present, 'is_paid' => true, 'is_active' => true,
            'sort_order' => (int) AttendanceStatus::query()->max('sort_order') + 10,
        ]));
    }

    public function store(AttendanceStatusRequest $request): RedirectResponse
    {
        $status = AttendanceStatus::create($request->recordData());

        return redirect()->route('admin.attendance-statuses.index')->with('status', __('schedules.statuses.saved', ['name' => $status->name]));
    }

    public function edit(AttendanceStatus $attendanceStatus): View
    {
        Gate::authorize('update', $attendanceStatus);

        return $this->form($attendanceStatus);
    }

    public function update(AttendanceStatusRequest $request, AttendanceStatus $attendanceStatus): RedirectResponse
    {
        $attendanceStatus->update($request->recordData());

        return redirect()->route('admin.attendance-statuses.index')->with('status', __('schedules.statuses.saved', ['name' => $attendanceStatus->name]));
    }

    public function destroy(AttendanceStatus $attendanceStatus, DeleteStructureRecord $delete): RedirectResponse
    {
        Gate::authorize('delete', $attendanceStatus);

        $delete->handle($attendanceStatus);

        return redirect()->route('admin.attendance-statuses.index')->with('status', __('schedules.statuses.deleted', ['name' => $attendanceStatus->name]));
    }

    private function form(AttendanceStatus $status): View
    {
        return view('admin.scheduling.statuses.form', ['record' => $status, 'categories' => AttendanceStatusCategory::cases()]);
    }
}
