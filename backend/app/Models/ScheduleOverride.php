<?php

namespace App\Models;

use App\Enums\OverrideSource;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\ScopedThroughEmployee;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A date-specific roster entry. When an employee has any override for a date,
 * the overrides replace the schedule pattern for that date; one with no shift
 * makes the date a rest day.
 */
#[Fillable(['employee_id', 'work_date', 'shift_id', 'shift_key', 'source', 'schedule_change_request_id', 'note', 'created_by'])]
class ScheduleOverride extends Model
{
    use Auditable, ScopedThroughEmployee;

    protected static function booted(): void
    {
        static::saving(function (ScheduleOverride $override): void {
            $override->shift_key = (int) ($override->shift_id ?? 0);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => OverrideSource::class,
            'work_date' => 'date',
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
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
