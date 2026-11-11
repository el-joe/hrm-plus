<?php

namespace App\Models;

use App\Enums\AssignmentChangeType;
use App\Enums\EmployeeStatus;
use App\Enums\TerminationType;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One effective-dated snapshot of an employee's job placement. Written by
 * App\Services\Employees\EmploymentHistory only; a row is never edited
 * afterwards except to mark it applied (scheduled rows) or cancelled.
 */
#[Fillable([
    'employee_id', 'change_type', 'effective_date', 'branch_id', 'department_id', 'team_id', 'position_id',
    'job_title_id', 'job_grade_id', 'employment_type_id', 'cost_center_id', 'manager_id', 'work_location_id',
    'status', 'termination_type', 'eligible_for_rehire', 'reason', 'applied_at', 'cancelled_at', 'created_by',
])]
class EmployeeAssignment extends Model
{
    use Auditable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'change_type' => AssignmentChangeType::class,
            'status' => EmployeeStatus::class,
            'termination_type' => TerminationType::class,
            'effective_date' => 'date',
            'eligible_for_rehire' => 'boolean',
            'applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Future-dated rows waiting for their effective date.
     *
     * @param  Builder<static>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('applied_at')->whereNull('cancelled_at');
    }

    public function isPending(): bool
    {
        return $this->applied_at === null && $this->cancelled_at === null;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<JobTitle, $this>
     */
    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    /**
     * @return BelongsTo<JobGrade, $this>
     */
    public function jobGrade(): BelongsTo
    {
        return $this->belongsTo(JobGrade::class);
    }

    /**
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
