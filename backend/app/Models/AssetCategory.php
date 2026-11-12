<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\AssetCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of company asset (laptop, mobile phone, SIM card, vehicle, uniform, ...).
 * Deleted only while no asset uses it; otherwise deactivated.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'description', 'requires_serial', 'is_active'])]
class AssetCategory extends Model
{
    /** @use HasFactory<AssetCategoryFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requires_serial' => 'boolean'];
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
