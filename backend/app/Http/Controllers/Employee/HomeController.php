<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\Announcements\AnnouncementFeed;
use App\Services\Attendance\Mobile\PortalCheckIn;
use App\Services\Employees\PortalHome;
use App\Services\Employees\PortalProfile;
use App\Services\HrRequests\PortalHrRequests;
use App\Services\Notifications\NotificationInbox;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Employee Home (spec section 5): profile summary, today's attendance and shift, this month's
 * attendance, leave balances, pending items, the latest payslip, upcoming shifts and holidays,
 * tasks, announcements and notifications, and the browser check-in / check-out
 * (PortalCheckIn). Figures come from App\Services\Employees\PortalHome
 * (shared with GET /api/v1/me/home).
 */
class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        PortalProfile $profile,
        PortalHome $home,
        PortalHrRequests $requests,
        AnnouncementFeed $announcements,
        NotificationInbox $inbox,
        PortalCheckIn $checkIn,
    ): View {
        $employee = $profile->employeeFor($request->user());

        return view('employee.home', [
            'user' => $request->user(),
            'employee' => $employee,
            'today' => $employee ? $home->attendanceToday($employee) : null,
            'checkIn' => $employee ? $checkIn->card($employee) : null,
            'month' => $employee ? $home->attendanceMonth($employee) : null,
            'leaveBalances' => $employee ? collect($home->leaveBalances($employee)) : collect(),
            'pending' => $employee ? $home->pending($employee) : null,
            'latestPayslip' => $employee ? $home->latestPayslip($employee) : null,
            'upcoming' => $employee ? $home->upcoming($employee) : null,
            'tasks' => $employee ? $home->tasks($employee) : [],
            'recentRequests' => $employee ? $requests->forEmployee($employee)->limit(3)->get() : collect(),
            'announcements' => $employee ? $announcements->latest($employee, 3) : collect(),
            'unreadAnnouncements' => $employee ? $announcements->unreadCount($employee) : 0,
            'notifications' => $inbox->latest($request->user(), 4),
            'unreadNotifications' => $inbox->unreadCount($request->user()),
        ]);
    }
}
