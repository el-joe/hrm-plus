<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Interview evaluation form: weighted criteria rated 1..score_scale.
 */
#[Fillable(['name_ar', 'name_en', 'description', 'is_active'])]
class EvaluationForm extends Model
{
    use Auditable, HasActiveFlag, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected function hasCodeColumn(): bool
    {
        return false;
    }

    /**
     * @return HasMany<EvaluationCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(EvaluationCriterion::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }
}
