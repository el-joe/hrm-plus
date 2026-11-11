<?php

namespace App\Http\Resources\V1;

use App\Models\AttendanceDay;
use App\Services\Attendance\AttendanceClock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One processed attendance day of the employee (mobile app). Instants are
 * ISO-8601 UTC; *_local are wall-clock times of the branch timezone.
 *
 * @mixin AttendanceDay
 */
class AttendanceDayResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->work_date->toDateString(),
            'timezone' => $this->timezone,
            'shift' => $this->shift ? ['id' => $this->shift->id, 'code' => $this->shift->code, 'name' => $this->shift->name, 'start' => substr((string) $this->shift->start_time, 0, 5), 'end' => substr((string) $this->shift->end_time, 0, 5)] : null,
            'status' => $this->status->value,
            'status_label' => $this->statusLabel(),
            'is_manual' => $this->is_manual,
            'sources' => $this->sources ?? [],
            'scheduled_start_at' => $this->scheduled_start_at?->toIso8601ZuluString(),
            'scheduled_end_at' => $this->scheduled_end_at?->toIso8601ZuluString(),
            'check_in_at' => $this->first_in_at?->toIso8601ZuluString(),
            'check_out_at' => $this->last_out_at?->toIso8601ZuluString(),
            'check_in_local' => AttendanceClock::localTime($this->first_in_at, $this->timezone),
            'check_out_local' => AttendanceClock::localTime($this->last_out_at, $this->timezone),
            'scheduled_minutes' => $this->scheduled_minutes,
            'worked_minutes' => $this->worked_minutes,
            'late_minutes' => $this->late_minutes,
            'early_leave_minutes' => $this->early_leave_minutes,
            'break_minutes' => $this->break_minutes,
            'overtime_minutes' => $this->overtime_minutes,
            'overtime_status' => $this->overtime_status->value,
            'overtime_approved_minutes' => $this->overtime_approved_minutes,
            'missing_check_in' => $this->hasFlag('missing_check_in'),
            'missing_check_out' => $this->hasFlag('missing_check_out'),
            'flags' => array_values(array_map(fn (string $flag): array => ['code' => $flag, 'label' => __('attendance.flags.'.$flag)], $this->flags ?? [])),
            'holiday' => $this->holiday?->name,
        ];
    }
}
