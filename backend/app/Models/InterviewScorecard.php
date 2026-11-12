<?php

namespace App\Models;

use App\Enums\ScorecardRecommendation;
use App\Models\Concerns\Auditable;
use Database\Factories\InterviewScorecardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One interviewer's evaluation of an interview.
 */
#[Fillable(['interview_id', 'user_id', 'recommendation', 'overall_score', 'comments', 'submitted_at'])]
class InterviewScorecard extends Model
{
    /** @use HasFactory<InterviewScorecardFactory> */
    use Auditable, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recommendation' => ScorecardRecommendation::class,
            'overall_score' => 'decimal:2',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Interview, $this>
     */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return HasMany<InterviewScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(InterviewScore::class);
    }
}
