<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dashboard\DashboardFilterRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\EmployeeGroup;
use App\Services\Authorization\DataScopeManager;
use App\Services\Dashboard\AdminDashboard;
use Illuminate\View\View;

/**
 * Admin dashboard (spec module 1): the permitted widgets in the admin's order, the shared filter
 * bar (date range, branch, department, employee group — kept in the query string), recent activity.
 */
class DashboardController extends Controller
{
    public function __invoke(DashboardFilterRequest $request, AdminDashboard $dashboard, DataScopeManager $dataScopes): View
    {
        $user = $request->user();
        $filters = $dashboard->filters($request->safe()->except('refresh'));
        $canSeeData = $user->can('dashboard.view');

        return view('admin.dashboard', [
            'user' => $user,
            'filters' => $filters,
            'widgets' => $dashboard->widgets($user, $filters, $request->boolean('refresh')),
            'recentActivity' => $dashboard->recentActivity($user),
            'dataScopes' => $user->hasDataRestrictions() ? $dataScopes->describe($user->dataScopeIds()) : [],
            'branches' => $canSeeData ? Branch::query()->orderBy('code')->get() : collect(),
            'departments' => $canSeeData ? Department::query()->orderBy('code')->get() : collect(),
            'groups' => $canSeeData ? EmployeeGroup::query()->orderBy('code')->get() : collect(),
        ]);
    }
}
