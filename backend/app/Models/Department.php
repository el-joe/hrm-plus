<?php

namespace App\Models;

use App\Enums\DataScopeType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasDataScope;
use App\Models\Concerns\HasHierarchy;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A department. Departments form a tree (parent_id) and may belong to one
 * branch (branch_id) or be company-wide (null).
 */
#[Fillable(['code', 'name_ar', 'name_en', 'parent_id', 'branch_id', 'cost_center_id', 'head_position_id', 'description', 'is_active'])]
class Department extends Model implements GuardsDeletion
{
    /** @use HasFactory<DepartmentFactory> */
    use Auditable, HasActiveFlag, HasDataScope, HasFactory, HasHierarchy, HasLocalizedName;

    public function hierarchyParentColumn(): string
    {
        return 'parent_id';
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'branch_id',
            'department' => 'id',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * The position whose holder heads the department.
     *
     * @return BelongsTo<Position, $this>
     */
    public function headPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'head_position_id');
    }

    /**
     * @return HasMany<Team, $this>
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * @return HasMany<Position, $this>
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'child_departments' => $this->children()->count(),
            'teams' => $this->teams()->count(),
            'positions' => $this->positions()->count(),
            'employees' => Employee::query()->withoutGlobalScopes()->where('department_id', $this->getKey())->count(),
            'admin_scopes' => AdminDataScope::query()
                ->where('scope_type', DataScopeType::Department)->where('scope_id', $this->getKey())->count(),
        ];
    }
}
