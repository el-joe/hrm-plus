<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Enums\WorkLocationScope;
use App\Http\Controllers\Controller;
use App\Models\AttendanceWorkLocationRule;
use App\Models\BranchLocation;
use App\Models\Department;
use App\Models\Employee;
use App\Rules\ExistsInScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mobile work locations: by default an employee checks in at any active
 * location of their branch; rules here replace that for one employee or a
 * whole department (a named location of any branch, or anywhere = remote
 * work), optionally for a period. Routes require attendance.manage.
 */
class WorkLocationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['scope_type' => ['nullable', Rule::enum(WorkLocationScope::class)]]);
        $employeeIds = Employee::query()->select('id');
        $departmentIds = Department::query()->select('id');

        $rules = AttendanceWorkLocationRule::query()->with('location.branch')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('scope_type', WorkLocationScope::Employee)->whereIn('scope_id', $employeeIds))
                ->orWhere(fn (Builder $query) => $query->where('scope_type', WorkLocationScope::Department)->whereIn('scope_id', $departmentIds)))
            ->when($filters['scope_type'] ?? null, fn (Builder $query, string $type) => $query->where('scope_type', $type))
            ->latest('id')->paginate(25)->withQueryString();

        return view('admin.attendance.work-locations', [
            'rules' => $rules,
            'filters' => $filters,
            'employees' => Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'departments' => Department::query()->active()->orderBy('code')->get(),
            'locations' => BranchLocation::query()->withoutGlobalScopes()->with('branch')->where('is_active', true)->orderBy('branch_id')->get(),
            'scopes' => WorkLocationScope::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope_type' => ['required', Rule::enum(WorkLocationScope::class)],
            'employee_id' => ['required_if:scope_type,employee', 'nullable', 'integer', new ExistsInScope(Employee::class)],
            'department_id' => ['required_if:scope_type,department', 'nullable', 'integer', new ExistsInScope(Department::class)],
            'allow_anywhere' => ['sometimes', 'boolean'],
            'branch_location_id' => ['nullable', 'required_unless:allow_anywhere,1', 'integer', Rule::exists(BranchLocation::class, 'id')->where('is_active', true)],
            'effective_from' => ['nullable', 'date_format:Y-m-d'],
            'effective_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [], trans('mobile.locations.fields'));

        $anywhere = $request->boolean('allow_anywhere');

        AttendanceWorkLocationRule::query()->create([
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_type'] === WorkLocationScope::Employee->value ? $data['employee_id'] : $data['department_id'],
            'branch_location_id' => $anywhere ? null : $data['branch_location_id'],
            'allow_anywhere' => $anywhere,
            'effective_from' => $data['effective_from'] ?? null,
            'effective_to' => $data['effective_to'] ?? null,
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', __('mobile.locations.saved'));
    }

    public function destroy(AttendanceWorkLocationRule $rule): RedirectResponse
    {
        $visible = $rule->scope_type === WorkLocationScope::Employee
            ? Employee::query()->whereKey($rule->scope_id)->exists()
            : Department::query()->whereKey($rule->scope_id)->exists();
        abort_unless($visible, 404);

        $rule->delete();

        return back()->with('status', __('mobile.locations.removed'));
    }
}
