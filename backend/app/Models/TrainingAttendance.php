<?php

namespace App\Models;

use App\Enums\TrainingAttendanceStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attendance of one participant on one session day.
 */
#[Fillable(['training_enrolment_id', 'date', 'status', 'note', 'recorded_by'])]
class TrainingAttendance extends Model
{
    use Auditable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['date' => 'date', 'status' => TrainingAttendanceStatus::class];
    }

    /**
     * @return BelongsTo<TrainingEnrolment, $this>
     */
    public function enrolment(): BelongsTo
    {
        return $this->belongsTo(TrainingEnrolment::class, 'training_enrolment_id');
    }
}
