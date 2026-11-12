<?php

namespace App\Models;

use App\Enums\PerformanceCycleStatus;
use App\Enums\PerformanceCycleType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\PerformanceCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A review cycle (annual, semi-annual, quarterly, probation). Drafts are
 * configured, launching creates one review per participant
 * (App\Actions\Performance\ManagePerformanceCycle), closing freezes it.
 * Company-wide configuration: not data-scoped (its reviews are).
 */
#[Fillable([
    'code', 'name_ar', 'name_en', 'type', 'period_start', 'period_end', 'rating_scale_id', 'goals_weight', 'competencies_weight',
    'self_assessment_enabled', 'self_assessment_due', 'manager_assessment_due', 'branch_id', 'department_id', 'description', 'created_by',
])]
class PerformanceCycle extends Model
{
    /** @use HasFactory<PerformanceCycleFactory> */
    use Auditable, HasFactory, HasLocalizedName;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'draft'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PerformanceCycleType::class,
            'status' => PerformanceCycleStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'goals_weight' => 'decimal:2',
            'competencies_weight' => 'decimal:2',
            'self_assessment_enabled' => 'boolean',
            'self_assessment_due' => 'date',
            'manager_assessment_due' => 'date',
            'launched_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RatingScale, $this>
     */
    public function ratingScale(): BelongsTo
    {
        return $this->belongsTo(RatingScale::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<PerformanceCycleCompetency, $this>
     */
    public function competencies(): HasMany
    {
        return $this->hasMany(PerformanceCycleCompetency::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<PerformanceReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class);
    }

    /**
     * @return HasMany<PerformanceGoal, $this>
     */
    public function goals(): HasMany
    {
        return $this->hasMany(PerformanceGoal::class);
    }

    public function isDraft(): bool
    {
        return $this->status === PerformanceCycleStatus::Draft;
    }

    public function isActive(): bool
    {
        return $this->status === PerformanceCycleStatus::Active;
    }

    public function isClosed(): bool
    {
        return $this->status === PerformanceCycleStatus::Closed;
    }
}
