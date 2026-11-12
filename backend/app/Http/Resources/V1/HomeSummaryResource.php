<?php

namespace App\Http\Resources\V1;

use App\Models\Employee;
use App\Models\User;
use App\Services\Announcements\AnnouncementFeed;
use App\Services\Employees\PortalHome;
use App\Services\Leave\Data\LeaveBalance;
use App\Services\Notifications\NotificationInbox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The home screen summary of an employee (GET /api/v1/me/home). Every figure comes from
 * App\Services\Employees\PortalHome — the same numbers as the portal home page.
 *
 * @mixin Employee
 */
class HomeSummaryResource extends JsonResource
{
    private ?User $user = null;

    public function forUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    private function home(): PortalHome
    {
        return app(PortalHome::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $home = $this->home();
        $user = $this->user ?? $request->user();
        /** @var Employee $employee */
        $employee = $this->resource;
        $payslip = $home->latestPayslip($employee);
        $unread = $home->unread($employee, $user);

        return [
            'employee' => [
                'id' => $this->id,
                'employee_number' => $this->employee_number,
                'name' => $this->name,
                'job_title' => $this->jobTitle?->name,
                'department' => $this->department?->name,
                'branch' => $this->branch?->name,
                'manager' => $this->manager?->name,
                'photo_url' => $this->photo_path !== null ? route('api.v1.me.profile.photo') : null,
            ],
            'attendance_today' => $home->attendanceToday($employee),
            'attendance_month' => $home->attendanceMonth($employee),
            'leave_balances' => array_map(fn (LeaveBalance $balance): array => [
                'leave_type' => [
                    'id' => $balance->type->id,
                    'code' => $balance->type->code,
                    'name' => $balance->type->name,
                    'color' => $balance->type->color,
                    'unit' => $balance->type->unit->value,
                ],
                'entitlement' => $balance->entitlement,
                'used' => $balance->used,
                'pending' => $balance->pending,
                'balance' => $balance->balance,
                'available' => $balance->available,
            ], $home->leaveBalances($employee)),
            'pending' => $home->pending($employee),
            'latest_payslip' => $payslip ? [
                'id' => $payslip->id,
                'number' => $payslip->number,
                'period_name' => $payslip->period->name,
                'period_end' => $payslip->period->end_date->toDateString(),
                'pay_date' => $payslip->period->pay_date->toDateString(),
                'currency' => $payslip->currency,
                'net_pay' => (string) $payslip->net_pay,
                'payment_status' => $payslip->payment_status->value,
                'payment_status_label' => $payslip->payment_status->label(),
                'pdf_url' => route('api.v1.me.payslips.pdf', $payslip->id),
            ] : null,
            'upcoming' => $home->upcoming($employee),
            'tasks' => $home->tasks($employee),
            'announcements' => AnnouncementResource::collection(app(AnnouncementFeed::class)->latest($employee, 3)),
            'unread_announcements' => $unread['announcements'],
            'notifications' => NotificationResource::collection(app(NotificationInbox::class)->query($user)->limit(5)->get()),
            'unread_notifications' => $unread['notifications'],
        ];
    }
}
