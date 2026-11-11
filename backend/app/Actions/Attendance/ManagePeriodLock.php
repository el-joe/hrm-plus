<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceAdjustment;
use App\Models\AttendanceDay;
use App\Models\AttendancePeriodLock;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendanceEngine;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

/**
 * Locking freezes the processed days of a period (all branches or one):
 * the days are processed one last time, then stamped with the lock. From
 * then on the engine writes attendance_adjustments instead of changing them;
 * payroll reads locked days plus their adjustments.
 *
 * Unlocking removes the stamp, marks the adjustments as superseded and
 * recalculates the days so they carry the full figures again.
 */
class ManagePeriodLock
{
    public function __construct(private readonly AttendanceEngine $engine, private readonly AuditLogger $audit) {}

    /**
     * @param  array{start_date: string, end_date: string, branch_id?: ?int, note?: ?string}  $data
     */
    public function lock(array $data, User $user): AttendancePeriodLock
    {
        $overlap = AttendancePeriodLock::query()->active()
            ->where('start_date', '<=', $data['end_date'])->where('end_date', '>=', $data['start_date'])
            ->where(fn (Builder $query) => $query->whereNull('branch_id')
                ->when($data['branch_id'] ?? null, fn (Builder $query, $branchId) => $query->orWhere('branch_id', $branchId), fn (Builder $query) => $query->orWhereNotNull('branch_id')))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => __('attendance.locks.overlap')]);
        }

        // Bring the period up to date before freezing it.
        $this->employees($data['start_date'], $data['branch_id'] ?? null)->each(
            fn (Employee $employee) => $this->engine->processRange($employee, $data['start_date'], $data['end_date']),
        );

        return DB::transaction(function () use ($data, $user): AttendancePeriodLock {
            $lock = AttendancePeriodLock::create([
                'start_date' => $data['start_date'], 'end_date' => $data['end_date'], 'branch_id' => $data['branch_id'] ?? null,
                'note' => $data['note'] ?? null, 'locked_by' => $user->id, 'locked_at' => now(),
            ]);

            $count = $this->days($lock)->whereNull('attendance_period_lock_id')->update(['attendance_period_lock_id' => $lock->id]);
            $this->audit->log('attendance.period_locked', $lock, meta: ['days' => $count], actor: $user);

            return $lock;
        });
    }

    public function unlock(AttendancePeriodLock $lock, User $user): void
    {
        if (! $lock->isActive()) {
            return;
        }

        DB::transaction(function () use ($lock, $user): void {
            $dayIds = AttendanceDay::query()->withoutGlobalScopes()->where('attendance_period_lock_id', $lock->id)->pluck('id');
            AttendanceAdjustment::query()->whereIn('attendance_day_id', $dayIds)->whereNull('superseded_at')->update(['superseded_at' => now()]);
            AttendanceDay::query()->withoutGlobalScopes()->whereIn('id', $dayIds)->update(['attendance_period_lock_id' => null]);
            $lock->forceFill(['unlocked_at' => now(), 'unlocked_by' => $user->id])->save();
            $this->audit->log('attendance.period_unlocked', $lock, meta: ['days' => $dayIds->count()], actor: $user);
        });

        $this->employees($lock->start_date->format('Y-m-d'), $lock->branch_id)->each(
            fn (Employee $employee) => $this->engine->processRange($employee, $lock->start_date, $lock->end_date),
        );
    }

    /**
     * @return Builder<AttendanceDay>
     */
    private function days(AttendancePeriodLock $lock): Builder
    {
        return AttendanceDay::query()->withoutGlobalScopes()
            ->whereBetween('work_date', [$lock->start_date->format('Y-m-d'), $lock->end_date->format('Y-m-d')])
            ->when($lock->branch_id, fn (Builder $query, $branchId) => $query->where('branch_id', $branchId));
    }

    /**
     * @return LazyCollection<int, Employee>
     */
    private function employees(string $from, ?int $branchId): LazyCollection
    {
        return Employee::query()->withoutGlobalScopes()
            ->where(fn (Builder $query) => $query->where('status', '!=', 'terminated')->orWhere('termination_date', '>=', $from))
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->orderBy('id')->lazyById(100);
    }
}
