<?php

namespace App\Http\Controllers\Employee;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\Announcements\AnnouncementFeed;
use App\Services\Employees\PortalProfile;
use App\Services\HrRequests\PortalHrRequests;
use App\Services\Leave\LeaveBalanceCalculator;
use App\Services\Leave\LeaveEligibility;
use App\Services\Notifications\NotificationInbox;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        PortalProfile $profile,
        LeaveBalanceCalculator $balances,
        LeaveEligibility $eligibility,
        PortalHrRequests $requests,
        AnnouncementFeed $announcements,
        NotificationInbox $inbox,
    ): View {
        $employee = $profile->employeeFor($request->user());

        return view('employee.home', [
            'user' => $request->user(),
            'employee' => $employee,
            // Employee Home (spec section 5): remaining leave balance and pending requests.
            'leaveBalances' => $employee ? $balances->all($employee, null, $eligibility->requestableTypes($employee))->filter->tracksBalance()->values() : collect(),
            'pendingLeave' => $employee ? LeaveRequest::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
                ->inStatus([LeaveRequestStatus::Pending, LeaveRequestStatus::CancellationPending])->count() : 0,
            // Phase 8b: open HR requests, latest announcements, latest notifications.
            'openRequests' => $employee ? $requests->openCount($employee) : 0,
            'recentRequests' => $employee ? $requests->forEmployee($employee)->limit(3)->get() : collect(),
            'announcements' => $employee ? $announcements->latest($employee, 3) : collect(),
            'unreadAnnouncements' => $employee ? $announcements->unreadCount($employee) : 0,
            'pendingAcknowledgements' => $employee ? $announcements->pendingAcknowledgements($employee) : 0,
            'notifications' => $inbox->latest($request->user(), 4),
            'unreadNotifications' => $inbox->unreadCount($request->user()),
        ]);
    }
}
