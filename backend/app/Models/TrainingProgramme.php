<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\TrainingProgrammeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A training programme: a named group of courses (e.g. "Leadership development").
 */
#[Fillable(['code', 'name_ar', 'name_en', 'description', 'is_active'])]
class TrainingProgramme extends Model
{
    /** @use HasFactory<TrainingProgrammeFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return HasMany<TrainingCourse, $this>
     */
    public function courses(): HasMany
    {
        return $this->hasMany(TrainingCourse::class);
    }

    public function isInUse(): bool
    {
        return $this->courses()->exists();
    }
}
