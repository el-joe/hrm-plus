<?php

namespace App\Models;

use App\Enums\StageCategory;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasLocalizedName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pipeline stage: company default (vacancy_id NULL) or a vacancy's own.
 * Read pipelines through App\Services\Recruitment\Pipeline.
 */
#[Fillable(['vacancy_id', 'code', 'name_ar', 'name_en', 'category', 'sort_order', 'notify_candidate', 'is_active'])]
class RecruitmentStage extends Model
{
    use Auditable, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => StageCategory::class,
            'notify_candidate' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeDefaultPipeline(Builder $query): void
    {
        $query->whereNull('vacancy_id');
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return BelongsTo<Vacancy, $this>
     */
    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    /**
     * @return HasMany<JobApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'stage_id');
    }
}
