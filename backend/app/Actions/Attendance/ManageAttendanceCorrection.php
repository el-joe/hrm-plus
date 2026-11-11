<?php

namespace App\Actions\Attendance;

use App\Enums\CorrectionSource;
use App\Enums\CorrectionStatus;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\AttendancePolicyResolver;
use App\Services\Attendance\LocalTime;
use App\Services\Attendance\ManualAttendanceWriter;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attendance corrections (individual entry). An employee's request (portal /
 * API) always goes through the approval engine (workflow
 * "attendance_correction"). An admin's correction is applied at once unless
 * the employee's attendance policy says corrections need approval.
 * Applying writes manual raw events and recalculates the day.
 */
class ManageAttendanceCorrection
{
    public function __construct(
        private readonly ManualAttendanceWriter $writer,
        private readonly ApprovalService $approvals,
        private readonly ScheduleResolver $schedules,
        private readonly AttendancePolicyResolver $policies,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{work_date: string, shift_id?: ?int, check_in?: ?string, check_out?: ?string, attendance_status_id?: ?int, void_raw_event_ids?: list<int>, reason: string}  $data
     */
    public function create(Employee $employee, array $data, CorrectionSource $source, User $user): AttendanceCorrection
    {
        $date = AttendanceClock::date($data['work_date']);
        $resolved = $this->schedules->for($employee, $date);

        if (! $resolved->isEmployed()) {
            throw ValidationException::withMessages(['work_date' => __('attendance.corrections.not_employed')]);
        }

        if (empty($data['check_in']) && empty($data['check_out']) && empty($data['attendance_status_id']) && empty($data['void_raw_event_ids'])) {
            throw ValidationException::withMessages(['check_in' => __('attendance.corrections.nothing_requested')]);
        }

        $shift = collect($resolved->shifts)->first(fn ($shift): bool => $shift->key() === (int) ($data['shift_id'] ?? 0)) ?? ($resolved->shifts[0] ?? null);
        $checkIn = filled($data['check_in'] ?? null) ? LocalTime::toInstant($date, $data['check_in'], $resolved->timezone, $shift?->windowStart->subMinute()) : null;
        $checkOut = filled($data['check_out'] ?? null)
            ? LocalTime::toInstant($date, $data['check_out'], $resolved->timezone, $checkIn ?? $shift?->startAt ?? $date)
            : null;

        $requiresApproval = $source !== CorrectionSource::Admin
            || $this->policies->for($employee, $resolved->placement, $date)->correction_requires_approval;

        return DB::transaction(function () use ($employee, $data, $source, $user, $date, $shift, $checkIn, $checkOut, $requiresApproval): AttendanceCorrection {
            $correction = AttendanceCorrection::create([
                'employee_id' => $employee->id,
                'work_date' => $date->format('Y-m-d'),
                'shift_id' => $data['shift_id'] ?? $shift?->key(),
                'check_in_at' => $checkIn,
                'check_out_at' => $checkOut,
                'attendance_status_id' => $data['attendance_status_id'] ?? null,
                'void_raw_event_ids' => array_values(array_map('intval', $data['void_raw_event_ids'] ?? [])) ?: null,
                'reason' => $data['reason'],
                'source' => $source,
                'requested_by' => $user->id,
            ]);

            if ($requiresApproval) {
                $this->approvals->submit($correction, AttendanceCorrection::WORKFLOW, $user);
            } else {
                $this->apply($correction, $user);
            }

            return $correction->refresh();
        });
    }

    public function apply(AttendanceCorrection $correction, ?User $user): void
    {
        DB::transaction(function () use ($correction, $user): void {
            $this->writer->applyCorrection($correction, $user);
            $correction->forceFill(['status' => CorrectionStatus::Applied, 'applied_at' => now(), 'decided_at' => $correction->decided_at ?? now()])->save();
            $this->audit->log('attendance.correction_applied', $correction, meta: ['work_date' => $correction->work_date->format('Y-m-d')], actor: $user);
        });
    }

    public function applyApprovalOutcome(AttendanceCorrection $correction, bool $approved, ?User $decidedBy): void
    {
        if (! $correction->isPending()) {
            return;
        }

        if ($approved) {
            $correction->forceFill(['decided_at' => now()])->save();
            $this->apply($correction, $decidedBy);

            return;
        }

        $correction->forceFill(['status' => CorrectionStatus::Rejected, 'decided_at' => now()])->save();
    }

    /**
     * The requester withdraws a pending correction.
     */
    public function cancel(AttendanceCorrection $correction, User $user): void
    {
        $request = $correction->latestApprovalRequest;

        if (! $correction->isPending() || $request === null) {
            throw ValidationException::withMessages(['correction' => __('attendance.corrections.not_pending')]);
        }

        DB::transaction(function () use ($correction, $request, $user): void {
            $this->approvals->cancel($request, $user);
            $correction->forceFill(['status' => CorrectionStatus::Cancelled, 'decided_at' => now()])->save();
        });
    }
}
