<?php

namespace App\Models;

use App\Enums\AttendanceScope;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\DescribesAttendanceScope;
use Database\Factories\ScheduleAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assigns a work schedule to an employee, an employee group, a department, a
 * branch or the whole company from effective_from (to effective_to, inclusive).
 */
#[Fillable(['work_schedule_id', 'scope_type', 'scope_id', 'effective_from', 'effective_to', 'note', 'created_by'])]
class ScheduleAssignment extends Model
{
    /** @use HasFactory<ScheduleAssignmentFactory> */
    use Auditable, DescribesAttendanceScope, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_type' => AttendanceScope::class,
            'scope_id' => 'integer',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<WorkSchedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }
}
