<?php

namespace App\Actions\Attendance;

use App\Enums\OverrideSource;
use App\Enums\RequestStatus;
use App\Enums\ScheduleChangeType;
use App\Models\Employee;
use App\Models\ScheduleChangeRequest;
use App\Models\ScheduleOverride;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\AttendanceRecalculation;
use App\Services\Attendance\ScheduleResolver;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shift changes and swaps through the approval engine (workflow
 * "schedule_change"), and direct roster overrides from the calendar.
 * Approval writes schedule_overrides (one per shift, none = rest day) and
 * recalculates the affected days.
 */
class ManageScheduleChange
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly AttendanceRecalculation $recalculation,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{type: string, employee_id: int, work_date: string, shift_id?: ?int, swap_employee_id?: ?int, reason: string}  $data
     */
    public function request(array $data, User $user): ScheduleChangeRequest
    {
        $type = ScheduleChangeType::from($data['type']);

        if ($type === ScheduleChangeType::Swap && (int) ($data['swap_employee_id'] ?? 0) === (int) $data['employee_id']) {
            throw ValidationException::withMessages(['swap_employee_id' => __('attendance.schedule_changes.swap_self')]);
        }

        return DB::transaction(function () use ($data, $type, $user): ScheduleChangeRequest {
            $request = ScheduleChangeRequest::create([
                'type' => $type,
                'employee_id' => $data['employee_id'],
                'work_date' => $data['work_date'],
                'shift_id' => $type === ScheduleChangeType::Change ? ($data['shift_id'] ?? null) : null,
                'swap_employee_id' => $type === ScheduleChangeType::Swap ? $data['swap_employee_id'] : null,
                'reason' => $data['reason'],
                'requested_by' => $user->id,
            ]);
            $this->approvals->submit($request, ScheduleChangeRequest::WORKFLOW, $user);

            return $request;
        });
    }

    public function applyApprovalOutcome(ScheduleChangeRequest $request, bool $approved, ?User $decidedBy): void
    {
        if (! $request->isPending()) {
            return;
        }

        DB::transaction(function () use ($request, $approved, $decidedBy): void {
            $request->forceFill(['status' => $approved ? RequestStatus::Approved : RequestStatus::Rejected, 'decided_at' => now()])->save();

            if (! $approved) {
                return;
            }

            $date = AttendanceClock::date($request->work_date);
            $employee = Employee::query()->withoutGlobalScopes()->findOrFail($request->employee_id);

            if ($request->type === ScheduleChangeType::Change) {
                $this->setOverride($employee, $date->format('Y-m-d'), array_filter([$request->shift_id]), $decidedBy, OverrideSource::ChangeRequest, $request);

                return;
            }

            $other = Employee::query()->withoutGlobalScopes()->findOrFail($request->swap_employee_id);
            $resolver = app(ScheduleResolver::class);
            $mine = array_map(fn ($shift): int => $shift->key(), $resolver->for($employee, $date)->shifts);
            $theirs = array_map(fn ($shift): int => $shift->key(), $resolver->for($other, $date)->shifts);

            $this->setOverride($employee, $date->format('Y-m-d'), $theirs, $decidedBy, OverrideSource::Swap, $request);
            $this->setOverride($other, $date->format('Y-m-d'), $mine, $decidedBy, OverrideSource::Swap, $request);
        });
    }

    /**
     * Replace the employee's roster for a date: the given shifts, or a rest day when empty.
     *
     * @param  list<int>  $shiftIds
     */
    public function setOverride(Employee $employee, string $date, array $shiftIds, ?User $user, OverrideSource $source = OverrideSource::Manual, ?ScheduleChangeRequest $request = null, ?string $note = null): void
    {
        DB::transaction(function () use ($employee, $date, $shiftIds, $user, $source, $request, $note): void {
            ScheduleOverride::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->whereDate('work_date', $date)->get()
                ->each(fn (ScheduleOverride $override) => $override->delete());

            foreach (array_values(array_unique($shiftIds)) ?: [null] as $shiftId) {
                ScheduleOverride::create([
                    'employee_id' => $employee->id, 'work_date' => $date, 'shift_id' => $shiftId, 'shift_key' => (int) ($shiftId ?? 0), 'source' => $source,
                    'schedule_change_request_id' => $request?->id, 'note' => $note, 'created_by' => $user?->id,
                ]);
            }
        });

        $this->recalculation->now($employee->id, [$date]);
    }

    /**
     * Back to the schedule pattern for the date.
     */
    public function clearOverride(Employee $employee, string $date, ?User $user): void
    {
        $removed = ScheduleOverride::query()->withoutGlobalScopes()->where('employee_id', $employee->id)->whereDate('work_date', $date)->get();
        $removed->each(fn (ScheduleOverride $override) => $override->delete());

        if ($removed->isNotEmpty()) {
            $this->audit->log('schedule.override_cleared', $employee, meta: ['date' => $date], actor: $user);
            $this->recalculation->now($employee->id, [$date]);
        }
    }
}
