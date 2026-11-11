<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasDataScope;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\BranchLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A physical site of a branch with its GPS point and geofence radius. The
 * attendance engine (Phase 5) validates mobile check-ins against active
 * locations server-side.
 */
#[Fillable(['name_ar', 'name_en', 'address', 'latitude', 'longitude', 'radius_meters', 'is_active'])]
class BranchLocation extends Model implements GuardsDeletion
{
    /** @use HasFactory<BranchLocationFactory> */
    use Auditable, HasActiveFlag, HasDataScope, HasFactory, HasLocalizedName;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meters' => 'integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'branch_id',
            'department' => 'branch.departments.id',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Nothing references a location yet; Phase 5 adds attendance punches.
     *
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [];
    }

    protected function hasCodeColumn(): bool
    {
        return false;
    }
}
