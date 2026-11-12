<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\RatingScaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable rating scale (e.g. 1–5). Each level has the value a reviewer
 * gives an item and the minimum weighted score that earns it as the final rating.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'description', 'is_active'])]
class RatingScale extends Model implements GuardsDeletion
{
    /** @use HasFactory<RatingScaleFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * Levels, lowest value first.
     *
     * @return HasMany<RatingScaleLevel, $this>
     */
    public function levels(): HasMany
    {
        return $this->hasMany(RatingScaleLevel::class)->orderBy('value');
    }

    /**
     * @return HasMany<PerformanceCycle, $this>
     */
    public function cycles(): HasMany
    {
        return $this->hasMany(PerformanceCycle::class);
    }

    public function minValue(): string
    {
        return (string) ($this->levels->first()?->value ?? '0');
    }

    public function maxValue(): string
    {
        return (string) ($this->levels->last()?->value ?? '0');
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return ['performance_cycles' => $this->cycles()->count()];
    }
}
