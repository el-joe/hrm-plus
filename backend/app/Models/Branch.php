<?php

namespace App\Models;

use App\Enums\DataScopeType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasDataScope;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name_ar', 'name_en', 'is_headquarters', 'email', 'phone', 'country_code',
    'address_ar', 'address_en', 'timezone', 'manager_position_id', 'is_active',
])]
class Branch extends Model implements GuardsDeletion
{
    /** @use HasFactory<BranchFactory> */
    use Auditable, HasActiveFlag, HasDataScope, HasFactory, HasLocalizedName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_headquarters' => 'boolean',
        ];
    }

    /**
     * A branch-restricted admin sees their branches; a department-restricted
     * admin sees the branches their departments belong to.
     *
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'id',
            'department' => 'departments.id',
        ];
    }

    /**
     * @return HasMany<BranchLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(BranchLocation::class);
    }

    /**
     * @return HasMany<Department, $this>
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * The position whose holder manages the branch.
     *
     * @return BelongsTo<Position, $this>
     */
    public function managerPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'manager_position_id');
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'departments' => $this->departments()->count(),
            'employees' => Employee::query()->withoutGlobalScopes()->where('branch_id', $this->getKey())->count(),
            'admin_scopes' => AdminDataScope::query()
                ->where('scope_type', DataScopeType::Branch)->where('scope_id', $this->getKey())->count(),
        ];
    }
}
