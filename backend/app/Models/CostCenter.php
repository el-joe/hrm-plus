<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasHierarchy;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\CostCenterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name_ar', 'name_en', 'parent_id', 'description', 'is_active'])]
class CostCenter extends Model implements GuardsDeletion
{
    /** @use HasFactory<CostCenterFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasHierarchy, HasLocalizedName;

    public function hierarchyParentColumn(): string
    {
        return 'parent_id';
    }

    /**
     * @return BelongsTo<CostCenter, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class, 'parent_id');
    }

    /**
     * @return HasMany<CostCenter, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(CostCenter::class, 'parent_id');
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'child_cost_centers' => $this->children()->count(),
            'departments' => $this->departments()->count(),
            'employees' => Employee::query()->withoutGlobalScopes()->where('cost_center_id', $this->getKey())->count(),
        ];
    }
}
