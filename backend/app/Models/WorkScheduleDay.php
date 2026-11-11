<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One shift on one day of a work schedule pattern.
 */
#[Fillable(['day_index', 'shift_id'])]
class WorkScheduleDay extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['day_index' => 'integer'];
    }

    /**
     * @return BelongsTo<WorkSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
