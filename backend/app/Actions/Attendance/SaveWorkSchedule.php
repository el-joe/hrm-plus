<?php

namespace App\Actions\Attendance;

use App\Models\WorkSchedule;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a work schedule and replaces its day pattern in one
 * transaction. A pattern change is logged on the schedule as
 * "work_schedule.pattern_updated" (old and new shift ids per day).
 */
class SaveWorkSchedule
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, list<int>>  $pattern  day index => shift ids (empty = rest day)
     */
    public function handle(WorkSchedule $schedule, array $data, array $pattern): WorkSchedule
    {
        return DB::transaction(function () use ($schedule, $data, $pattern): WorkSchedule {
            $schedule->fill($data)->save();

            $before = $schedule->days()->get()->groupBy('day_index')->map(fn ($days) => $days->pluck('shift_id')->all())->all();
            $schedule->days()->delete();

            foreach ($pattern as $dayIndex => $shiftIds) {
                foreach ($shiftIds as $shiftId) {
                    $schedule->days()->create(['day_index' => $dayIndex, 'shift_id' => $shiftId]);
                }
            }

            $after = array_filter($pattern);

            if ($before != $after) {
                $this->audit->log('work_schedule.pattern_updated', $schedule, ['pattern' => $before], ['pattern' => $after]);
            }

            return $schedule->load('days');
        });
    }
}
