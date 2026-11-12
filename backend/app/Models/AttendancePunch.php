<?php

namespace App\Models;

use App\Enums\PunchDirection;
use App\Enums\RawEventSource;
use Database\Factories\AttendancePunchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A normalised punch derived 1:1 from a non-voided punch raw event. The
 * engine attributes it to an attendance day (attendance_day_id) and marks
 * repeats inside the policy's dedupe window as duplicates.
 */
class AttendancePunch extends Model
{
    /** @use HasFactory<AttendancePunchFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'direction' => PunchDirection::class,
            'source' => RawEventSource::class,
            'is_duplicate' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<AttendanceRawEvent, $this>
     */
    public function rawEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceRawEvent::class, 'attendance_raw_event_id');
    }

    /**
     * @return BelongsTo<AttendanceDay, $this>
     */
    public function day(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class, 'attendance_day_id');
    }
}
