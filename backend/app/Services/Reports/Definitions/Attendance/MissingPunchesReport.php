<?php

namespace App\Services\Reports\Definitions\Attendance;

use App\Enums\AttendanceDayStatus;
use App\Models\AttendanceDay;
use App\Models\User;
use App\Services\Reports\Column;
use Illuminate\Database\Eloquent\Builder;

/**
 * Days with a missing check-in or check-out: status "incomplete", or the engine flagged
 * missing_check_in / missing_check_out (also when it assumed the punch by policy).
 */
class MissingPunchesReport extends AttendanceDaysReport
{
    public function key(): string
    {
        return 'missing_punches';
    }

    public function columns(): array
    {
        return [...$this->dayColumns(), 'missing' => Column::text($this->label('missing')), 'status' => Column::text($this->label('status'))];
    }

    public function query(array $filters, User $user): Builder
    {
        return $this->days($filters)->where(fn (Builder $query) => $query
            ->where('attendance_days.status', AttendanceDayStatus::Incomplete->value)
            ->orWhereJsonContains('attendance_days.flags', 'missing_check_in')
            ->orWhereJsonContains('attendance_days.flags', 'missing_check_out'));
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var AttendanceDay $record */
        $flags = $record->flags ?? [];
        $missing = array_values(array_filter([
            in_array('missing_check_in', $flags, true) || ($record->first_in_at === null && $record->last_out_at !== null) ? __('attendance.flags.missing_check_in') : null,
            in_array('missing_check_out', $flags, true) || ($record->first_in_at !== null && $record->last_out_at === null) ? __('attendance.flags.missing_check_out') : null,
        ]));

        return [...$this->dayCells($record), 'missing' => implode(', ', $missing) ?: __('attendance.flags.missing_check_out'), 'status' => $record->statusLabel()];
    }
}
