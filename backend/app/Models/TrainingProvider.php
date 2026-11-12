<?php

namespace App\Models;

use App\Enums\TrainingProviderType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\TrainingProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Who delivers courses: an internal academy / department or an outside provider.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'type', 'contact_name', 'email', 'phone', 'website', 'is_active'])]
class TrainingProvider extends Model
{
    /** @use HasFactory<TrainingProviderFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => TrainingProviderType::class];
    }

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
