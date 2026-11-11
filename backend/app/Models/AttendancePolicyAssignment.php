<?php

namespace App\Models;

use App\Enums\AttendanceScope;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\DescribesAttendanceScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Assigns an attendance policy to an employee, group, employment type,
 * department, branch or the company for a date range.
 */
#[Fillable(['attendance_policy_id', 'scope_type', 'scope_id', 'effective_from', 'effective_to', 'created_by'])]
class AttendancePolicyAssignment extends Model
{
    use Auditable, DescribesAttendanceScope;

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
     * @return BelongsTo<AttendancePolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(AttendancePolicy::class, 'attendance_policy_id');
    }
}
