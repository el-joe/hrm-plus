<?php

namespace App\Actions\Attendance;

use App\Enums\OvertimeStatus;
use App\Models\AttendanceDay;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Approve (possibly fewer minutes) or reject the overtime of a processed day.
 * The decision survives recalculation while the calculated overtime stays
 * the same; multipliers stay in the policy version for payroll.
 */
class DecideOvertime
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(AttendanceDay $day, bool $approve, ?int $minutes, User $user): void
    {
        if ($day->overtime_minutes === 0) {
            throw ValidationException::withMessages(['overtime' => __('attendance.days.no_overtime')]);
        }

        if ($day->isLocked()) {
            throw ValidationException::withMessages(['overtime' => __('attendance.days.locked')]);
        }

        $approved = $approve ? min($day->overtime_minutes, $minutes ?? $day->overtime_minutes) : 0;
        $old = ['overtime_status' => $day->overtime_status->value, 'overtime_approved_minutes' => $day->overtime_approved_minutes];

        $day->forceFill([
            'overtime_status' => $approve ? OvertimeStatus::Approved : OvertimeStatus::Rejected,
            'overtime_approved_minutes' => $approved,
        ])->save();

        $this->audit->log('attendance.overtime_'.($approve ? 'approved' : 'rejected'), $day,
            $old, ['overtime_status' => $day->overtime_status->value, 'overtime_approved_minutes' => $approved], actor: $user);
    }
}
