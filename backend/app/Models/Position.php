<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasDataScope;
use App\Models\Concerns\HasHierarchy;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\PositionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An approved position (seat): a job title in a department, with a grade,
 * a headcount and the position it reports to. The reporting hierarchy and the
 * organisational chart are built from reports_to_position_id.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'department_id', 'job_title_id', 'job_grade_id', 'reports_to_position_id', 'headcount', 'description', 'is_active'])]
class Position extends Model implements GuardsDeletion
{
    /** @use HasFactory<PositionFactory> */
    use Auditable, HasActiveFlag, HasDataScope, HasFactory, HasHierarchy, HasLocalizedName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headcount' => 'integer',
        ];
    }

    public function hierarchyParentColumn(): string
    {
        return 'reports_to_position_id';
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'department.branch_id',
            'department' => 'department_id',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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
     * @return BelongsTo<Position, $this>
     */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'reports_to_position_id');
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(Position::class, 'reports_to_position_id');
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_position_id');
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function managedBranches(): HasMany
    {
        return $this->hasMany(Branch::class, 'manager_position_id');
    }

    /**
     * Employees placed in this position (any status; see occupants()).
     *
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Employees currently holding a seat (active or inactive, not terminated).
     *
     * @return HasMany<Employee, $this>
     */
    public function holders(): HasMany
    {
        return $this->employees()->employed();
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'direct_reports' => $this->directReports()->count(),
            'headed_departments' => $this->headedDepartments()->count(),
            'managed_branches' => $this->managedBranches()->count(),
            'employees' => Employee::query()->withoutGlobalScopes()->where('position_id', $this->getKey())->count(),
        ];
    }
}
