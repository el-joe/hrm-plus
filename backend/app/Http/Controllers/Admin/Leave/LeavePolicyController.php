<?php

namespace App\Http\Controllers\Admin\Leave;

use App\Actions\Leave\SaveLeavePolicy;
use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Enums\LeaveAccrualMethod;
use App\Enums\LeavePolicyScope;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Leave\LeavePolicyRequest;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Models\LeavePolicy;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Effective-dated leave policies per leave type and scope (company,
 * employment type, employee group). "New version" pre-fills the form from
 * an existing policy; saving it closes the earlier one (SaveLeavePolicy).
 */
class LeavePolicyController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', LeavePolicy::class);

        $filters = $this->filters($request, ['leave_type_id']);
        $filters += $request->validate(['current' => ['nullable', 'boolean']]);
        $today = now()->toDateString();

        $records = $this->applyFilters(LeavePolicy::query()->with(['leaveType', 'employmentType', 'employeeGroup']), $filters, ['leave_type_id'])
            ->when($request->boolean('current'), fn ($query) => $query->effectiveOn($today))
            ->orderBy('leave_type_id')->orderBy('scope_type')->orderByDesc('effective_from')
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.leave.policies.index', [
            'records' => $records,
            'filters' => $filters,
            'types' => LeaveType::query()->ordered()->get(),
            'today' => $today,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', LeavePolicy::class);

        $source = $request->integer('from') ? LeavePolicy::query()->find($request->integer('from')) : null;
        $policy = $source
            ? $source->replicate(['code', 'effective_from', 'effective_to'])->fill(['effective_from' => now()->addMonthNoOverflow()->startOfMonth()])
            : new LeavePolicy([
                'leave_type_id' => $request->integer('leave_type_id') ?: null, 'scope_type' => LeavePolicyScope::Company,
                'effective_from' => now()->startOfYear(), 'accrual_method' => LeaveAccrualMethod::AnnualUpfront, 'entitlement_days' => '0',
                'carry_over_max_days' => '0', 'allow_negative_balance' => (bool) setting('leave.allow_negative_balance'), 'is_active' => true,
            ]);

        return $this->form($policy, $source);
    }

    public function store(LeavePolicyRequest $request, SaveLeavePolicy $save): RedirectResponse
    {
        $policy = $save->handle($request->recordData());

        return redirect()->route('admin.leave-policies.index')->with('status', __('leave.policies.saved', ['name' => $policy->name]));
    }

    public function edit(LeavePolicy $leavePolicy): View
    {
        Gate::authorize('update', $leavePolicy);

        return $this->form($leavePolicy);
    }

    public function update(LeavePolicyRequest $request, LeavePolicy $leavePolicy, SaveLeavePolicy $save): RedirectResponse
    {
        $save->handle($request->recordData(), $leavePolicy);

        return redirect()->route('admin.leave-policies.index')->with('status', __('leave.policies.saved', ['name' => $leavePolicy->name]));
    }

    public function destroy(LeavePolicy $leavePolicy, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($leavePolicy, $delete, 'admin.leave-policies.index');
    }

    public function status(Request $request, LeavePolicy $leavePolicy, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $leavePolicy, $changeStatus);
    }

    private function form(LeavePolicy $policy, ?LeavePolicy $source = null): View
    {
        return view('admin.leave.policies.form', [
            'record' => $policy,
            'source' => $source,
            'types' => LeaveType::query()->activeOrKeep($policy->leave_type_id)->ordered()->get(),
            'scopes' => LeavePolicyScope::cases(),
            'methods' => LeaveAccrualMethod::cases(),
            'employmentTypes' => EmploymentType::query()->activeOrKeep($policy->scope_type === LeavePolicyScope::EmploymentType ? $policy->scope_id : null)->orderBy('code')->get(),
            'groups' => EmployeeGroup::query()->activeOrKeep($policy->scope_type === LeavePolicyScope::EmployeeGroup ? $policy->scope_id : null)->orderBy('code')->get(),
        ]);
    }
}
