<?php

namespace App\Models;

use App\Enums\TrainingDeliveryMode;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use Database\Factories\TrainingCourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A course of the training catalogue: provider, programme, delivery, duration,
 * cost per participant (DECIMAL), whether it issues a certificate (and for how
 * long it is valid) and the skills it teaches (level reached on completion).
 */
#[Fillable([
    'code', 'training_programme_id', 'training_provider_id', 'name_ar', 'name_en', 'description', 'delivery_mode',
    'duration_hours', 'cost_per_participant', 'issues_certificate', 'certificate_validity_months', 'is_active',
])]
class TrainingCourse extends Model
{
    /** @use HasFactory<TrainingCourseFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivery_mode' => TrainingDeliveryMode::class,
            'duration_hours' => 'decimal:2',
            'cost_per_participant' => 'decimal:2',
            'issues_certificate' => 'boolean',
            'certificate_validity_months' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<TrainingProgramme, $this>
     */
    public function programme(): BelongsTo
    {
        return $this->belongsTo(TrainingProgramme::class, 'training_programme_id');
    }

    /**
     * @return BelongsTo<TrainingProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(TrainingProvider::class, 'training_provider_id');
    }

    /**
     * @return HasMany<TrainingCourseSkill, $this>
     */
    public function courseSkills(): HasMany
    {
        return $this->hasMany(TrainingCourseSkill::class);
    }

    /**
     * @return HasMany<TrainingSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }

    public function isInUse(): bool
    {
        return $this->sessions()->exists();
    }
}
