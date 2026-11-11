<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Authorization\DataScopeManager;
use App\Services\Dashboard\AdminDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AdminDashboard $dashboard, DataScopeManager $dataScopes): View
    {
        $user = $request->user();

        return view('admin.dashboard', [
            'user' => $user,
            'stats' => $dashboard->stats($user),
            'recentActivity' => $dashboard->recentActivity($user),
            'dataScopes' => $user->hasDataRestrictions() ? $dataScopes->describe($user->dataScopeIds()) : [],
        ]);
    }
}
