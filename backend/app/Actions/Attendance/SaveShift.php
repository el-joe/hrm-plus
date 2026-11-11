<?php

namespace App\Actions\Attendance;

use App\Models\Shift;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a shift and replaces its breaks. Auditing: the shift is
 * Auditable; the break set is logged on the shift as "shift.breaks_updated".
 */
class SaveShift
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $breaks
     */
    public function handle(Shift $shift, array $data, array $breaks): Shift
    {
        return DB::transaction(function () use ($shift, $data, $breaks): Shift {
            $shift->fill($data)->save();

            $before = $shift->breaks()->get(['name', 'type', 'start_time', 'end_time', 'duration_minutes', 'is_paid'])->toArray();
            $shift->breaks()->delete();

            foreach (array_values($breaks) as $index => $break) {
                $shift->breaks()->create([...$break, 'sort_order' => $index]);
            }

            $after = $shift->breaks()->get(['name', 'type', 'start_time', 'end_time', 'duration_minutes', 'is_paid'])->toArray();

            if ($before !== $after) {
                app(AuditLogger::class)->log('shift.breaks_updated', $shift, ['breaks' => $before], ['breaks' => $after]);
            }

            return $shift->load('breaks');
        });
    }
}
