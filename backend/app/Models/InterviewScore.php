<?php

namespace App\Models;

use Database\Factories\InterviewScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['interview_scorecard_id', 'evaluation_criterion_id', 'score', 'comment'])]
class InterviewScore extends Model
{
    /** @use HasFactory<InterviewScoreFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    /**
     * @return BelongsTo<EvaluationCriterion, $this>
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(EvaluationCriterion::class, 'evaluation_criterion_id');
    }
}
