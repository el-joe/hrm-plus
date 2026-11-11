<?php

namespace App\Actions\Recruitment;

use App\Enums\InterviewStatus;
use App\Models\Interview;
use App\Models\InterviewScorecard;
use App\Models\User;
use App\Services\Recruitment\ScoreCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An interviewer submits (or revises) their scorecard: a score per
 * criterion of the interview's evaluation form, a recommendation and
 * comments. The weighted score and the interview / application averages are
 * recomputed with bcmath.
 */
class SubmitScorecard
{
    public function __construct(private readonly ScoreCalculator $scores) {}

    /**
     * @param  array{recommendation: string, comments?: string|null, scores?: array<int|string, int|string>, comments_by_criterion?: array<int|string, string|null>}  $data
     */
    public function handle(Interview $interview, User $interviewer, array $data): InterviewScorecard
    {
        if (! $interview->isInterviewer($interviewer)) {
            throw ValidationException::withMessages(['recommendation' => __('recruitment.validation.not_interviewer')]);
        }

        if (in_array($interview->status, [InterviewStatus::Cancelled, InterviewStatus::NoShow], true)) {
            throw ValidationException::withMessages(['recommendation' => __('recruitment.validation.interview_cancelled')]);
        }

        $criteria = $interview->evaluationForm?->criteria ?? collect();
        $scores = array_intersect_key($data['scores'] ?? [], $criteria->keyBy('id')->all());

        $scorecard = DB::transaction(function () use ($interview, $interviewer, $data, $criteria, $scores): InterviewScorecard {
            $scorecard = InterviewScorecard::query()->updateOrCreate(
                ['interview_id' => $interview->getKey(), 'user_id' => $interviewer->getKey()],
                [
                    'recommendation' => $data['recommendation'],
                    'comments' => $data['comments'] ?? null,
                    'overall_score' => $this->scores->weighted($criteria, $scores),
                    'submitted_at' => now(),
                ],
            );

            $scorecard->scores()->delete();

            foreach ($scores as $criterionId => $score) {
                $scorecard->scores()->create([
                    'evaluation_criterion_id' => (int) $criterionId,
                    'score' => (int) $score,
                    'comment' => $data['comments_by_criterion'][$criterionId] ?? null,
                ]);
            }

            if ($interview->status === InterviewStatus::Scheduled && $interview->scheduled_at->isPast()) {
                $interview->forceFill(['status' => InterviewStatus::Completed])->save();
            }

            return $scorecard;
        });

        $this->scores->refresh($interview);

        return $scorecard;
    }
}
