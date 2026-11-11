<?php

namespace App\Http\Controllers\Admin\Leave;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Enums\AttendanceStatusCategory;
use App\Enums\Gender;
use App\Enums\LeaveUnit;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Leave\LeaveTypeRequest;
use App\Models\ApprovalWorkflow;
use App\Models\AttendanceStatus;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Leave types: request rules, eligibility, counting and approval workflow.
 * In-use types are deactivated instead of deleted.
 */
class LeaveTypeController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', LeaveType::class);

        $filters = $this->filters($request);
        $records = $this->applyFilters(LeaveType::query()->withCount(['policies']), $filters)
            ->ordered()->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.leave.types.index', ['records' => $records, 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('create', LeaveType::class);

        return $this->form(new LeaveType([
            'is_paid' => true, 'tracks_balance' => true, 'unit' => LeaveUnit::Day, 'min_service_months' => 0, 'notice_days' => 0,
            'counts_rest_days' => (bool) setting('leave.count_weekends_as_leave'), 'counts_holidays' => false,
            'approval_workflow' => 'leave_request', 'color' => 'chart-1', 'sort_order' => 0, 'is_active' => true,
        ]));
    }

    public function store(LeaveTypeRequest $request): RedirectResponse
    {
        $type = LeaveType::create($request->recordData());

        return redirect()->route('admin.leave-types.index')->with('status', __('leave.types.saved', ['name' => $type->name]));
    }

    public function edit(LeaveType $leaveType): View
    {
        Gate::authorize('update', $leaveType);

        return $this->form($leaveType);
    }

    public function update(LeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($request->recordData());

        return redirect()->route('admin.leave-types.index')->with('status', __('leave.types.saved', ['name' => $leaveType->name]));
    }

    public function destroy(LeaveType $leaveType, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($leaveType, $delete, 'admin.leave-types.index');
    }

    public function status(Request $request, LeaveType $leaveType, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $leaveType, $changeStatus);
    }

    private function form(LeaveType $type): View
    {
        return view('admin.leave.types.form', [
            'record' => $type,
            'units' => LeaveUnit::cases(),
            'genders' => Gender::cases(),
            'workflows' => ApprovalWorkflow::query()->where('is_active', true)->withCount('steps')->orderBy('key')->get(),
            'statuses' => AttendanceStatus::query()->where('category', AttendanceStatusCategory::OnLeave)->orderBy('sort_order')->get(),
        ]);
    }
}
