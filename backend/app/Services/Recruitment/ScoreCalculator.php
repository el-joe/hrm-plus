<?php

namespace App\Services\Recruitment;

use App\Models\EvaluationCriterion;
use App\Models\Interview;
use Illuminate\Support\Collection;

/**
 * Interview scores, with bcmath (scale 4, stored with 2 decimals):
 * - a scorecard: Σ(score × weight) / Σ(weight) over the form's criteria;
 * - an interview: the mean of its scorecards' scores;
 * - an application: the mean of all its scorecards across interviews.
 */
class ScoreCalculator
{
    /**
     * @param  Collection<int, EvaluationCriterion>  $criteria
     * @param  array<int|string, int|string>  $scores  criterion id => score
     */
    public function weighted(Collection $criteria, array $scores): ?string
    {
        $sum = '0';
        $weights = '0';

        foreach ($criteria as $criterion) {
            if (! isset($scores[$criterion->getKey()])) {
                continue;
            }

            $weight = (string) max(1, $criterion->weight);
            $sum = bcadd($sum, bcmul((string) (int) $scores[$criterion->getKey()], $weight, 4), 4);
            $weights = bcadd($weights, $weight, 4);
        }

        return bccomp($weights, '0', 4) === 0 ? null : $this->round(bcdiv($sum, $weights, 4));
    }

    /**
     * @param  iterable<string|null>  $values
     */
    public function mean(iterable $values): ?string
    {
        $sum = '0';
        $count = 0;

        foreach ($values as $value) {
            if ($value !== null) {
                $sum = bcadd($sum, (string) $value, 4);
                $count++;
            }
        }

        return $count === 0 ? null : $this->round(bcdiv($sum, (string) $count, 4));
    }

    public function refresh(Interview $interview): void
    {
        $interview->forceFill(['average_score' => $this->mean($interview->scorecards()->pluck('overall_score'))])->save();

        $application = $interview->application()->withoutGlobalScopes()->firstOrFail();
        $scores = Interview::query()->withoutGlobalScopes()->where('job_application_id', $application->getKey())
            ->with('scorecards')->get()
            ->flatMap(fn (Interview $item) => $item->scorecards->pluck('overall_score'));

        $application->forceFill(['average_score' => $this->mean($scores)])->save();
    }

    /**
     * Half-up rounding to 2 decimals.
     */
    private function round(string $value): string
    {
        return bcadd($value, bccomp($value, '0', 4) >= 0 ? '0.005' : '-0.005', 2);
    }
}
