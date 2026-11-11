<?php

namespace App\Models;

use App\Enums\ManualEntryAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One employee / date (/ shift) line of a manual batch or import.
 */
#[Fillable(['employee_id', 'work_date', 'shift_id', 'shift_key', 'action', 'check_in_at', 'check_out_at', 'attendance_status_id', 'note'])]
class AttendanceManualEntry extends Model
{
    protected static function booted(): void
    {
        static::saving(function (AttendanceManualEntry $entry): void {
            $entry->shift_key = (int) ($entry->shift_id ?? 0);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ManualEntryAction::class,
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AttendanceManualBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(AttendanceManualBatch::class, 'attendance_manual_batch_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return BelongsTo<AttendanceStatus, $this>
     */
    public function attendanceStatus(): BelongsTo
    {
        return $this->belongsTo(AttendanceStatus::class);
    }

    /**
     * @return MorphMany<AttendanceRawEvent, $this>
     */
    public function rawEvents(): MorphMany
    {
        return $this->morphMany(AttendanceRawEvent::class, 'origin');
    }
}
