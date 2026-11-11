<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Onboarding checklist template. department / position / employment type
 * are matching criteria (NULL = any); App\Services\Onboarding\TemplateMatcher
 * picks the most specific active template for a new hire.
 */
#[Fillable(['name_ar', 'name_en', 'description', 'department_id', 'position_id', 'employment_type_id', 'is_active'])]
class OnboardingTemplate extends Model
{
    use Auditable, HasActiveFlag, HasLocalizedName;

    protected function hasCodeColumn(): bool
    {
        return false;
    }

    /**
     * @return HasMany<OnboardingTemplateTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(OnboardingTemplateTask::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Onboarding, $this>
     */
    public function onboardings(): HasMany
    {
        return $this->hasMany(Onboarding::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * @return BelongsTo<EmploymentType, $this>
     */
    public function employmentType(): BelongsTo
    {
        return $this->belongsTo(EmploymentType::class);
    }
}
