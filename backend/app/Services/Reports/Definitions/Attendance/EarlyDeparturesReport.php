<?php

namespace App\Services\Reports\Definitions\Attendance;

use App\Models\AttendanceDay;
use App\Models\User;
use App\Services\Reports\Column;
use Illuminate\Database\Eloquent\Builder;

/**
 * Days with an early departure (attendance_days.early_leave_minutes > 0).
 */
class EarlyDeparturesReport extends AttendanceDaysReport
{
    public function key(): string
    {
        return 'early_departures';
    }

    public function columns(): array
    {
        return [...$this->dayColumns(), 'early_minutes' => Column::number($this->label('early_minutes')), 'early_leave' => Column::duration($this->label('early_leave')), 'status' => Column::text($this->label('status'))];
    }

    public function sortable(): array
    {
        return [...parent::sortable(), 'early_minutes' => 'attendance_days.early_leave_minutes'];
    }

    public function query(array $filters, User $user): Builder
    {
        return $this->days($filters)->where('attendance_days.early_leave_minutes', '>', 0);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var AttendanceDay $record */
        return [...$this->dayCells($record), 'early_minutes' => (int) $record->early_leave_minutes, 'early_leave' => $this->minutes($record->early_leave_minutes), 'status' => $record->statusLabel()];
    }
}
