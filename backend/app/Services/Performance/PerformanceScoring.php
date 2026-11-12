<?php

namespace App\Services\Performance;

use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCompetency;
use App\Models\RatingScale;
use App\Models\RatingScaleLevel;

/**
 * Weighted scoring of performance reviews (bcmath, DECIMAL strings, 2 decimals
 * rounded half up; intermediate results keep 6 decimals):
 *
 * - goals score = Σ(rating × weight) / Σ weight over the rated, counted goals;
 * - competencies score = the same over the review's competencies;
 * - overall = goals × goals_weight % + competencies × competencies_weight %;
 *   when one side has nothing rated the other side counts 100 %;
 * - final rating = the level with the highest min_score that is ≤ overall.
 *
 * Scores are on the cycle's rating scale (e.g. 3.75 of 5).
 */
class PerformanceScoring
{
    private const SCALE = 6;

    /**
     * Weighted average of [rating, weight] pairs; pairs without a rating or with a zero weight
     * are skipped. Null when nothing counts.
     *
     * @param  iterable<array{0: string|int|float|null, 1: string|int|float|null}>  $items
     */
    public function weighted(iterable $items): ?string
    {
        $sum = '0';
        $weights = '0';

        foreach ($items as [$rating, $weight]) {
            if ($rating === null || $rating === '' || $weight === null || bccomp((string) $weight, '0', self::SCALE) <= 0) {
                continue;
            }

            $sum = bcadd($sum, bcmul((string) $rating, (string) $weight, self::SCALE), self::SCALE);
            $weights = bcadd($weights, (string) $weight, self::SCALE);
        }

        if (bccomp($weights, '0', self::SCALE) === 0) {
            return null;
        }

        return $this->round(bcdiv($sum, $weights, self::SCALE));
    }

    /**
     * Overall score from the two parts and the cycle's split (percentages).
     */
    public function overall(?string $goalsScore, ?string $competenciesScore, string $goalsWeight, string $competenciesWeight): ?string
    {
        if ($goalsScore === null && $competenciesScore === null) {
            return null;
        }

        if ($goalsScore === null || bccomp($goalsWeight, '0', 2) === 0) {
            return $competenciesScore ?? $goalsScore;
        }

        if ($competenciesScore === null || bccomp($competenciesWeight, '0', 2) === 0) {
            return $goalsScore;
        }

        $total = bcadd($goalsWeight, $competenciesWeight, self::SCALE);
        $value = bcdiv(bcadd(bcmul($goalsScore, $goalsWeight, self::SCALE), bcmul($competenciesScore, $competenciesWeight, self::SCALE), self::SCALE), $total, self::SCALE);

        return $this->round($value);
    }

    /**
     * The final rating band of a score on a scale (levels loaded or not).
     */
    public function levelFor(RatingScale $scale, ?string $score): ?RatingScaleLevel
    {
        if ($score === null) {
            return null;
        }

        return $scale->levels
            ->filter(fn (RatingScaleLevel $level): bool => bccomp((string) $level->min_score, $score, 2) <= 0)
            ->sortBy(fn (RatingScaleLevel $level): string => str_pad((string) $level->min_score, 12, '0', STR_PAD_LEFT))
            ->last();
    }

    /**
     * Scores of a review from the self ratings ("self") or the manager ratings ("manager").
     *
     * @return array{goals: ?string, competencies: ?string, overall: ?string}
     */
    public function scoreReview(PerformanceReview $review, string $side): array
    {
        $column = $side === 'self' ? 'self_rating' : 'manager_rating';
        $cycle = $review->cycle;

        $goals = $this->weighted($review->goalsQuery()->counted()->get()
            ->map(fn (PerformanceGoal $goal): array => [$goal->{$column}, $goal->weight]));
        $competencies = $this->weighted($review->competencies()->get()
            ->map(fn (PerformanceReviewCompetency $row): array => [$row->{$column}, $row->weight]));

        return [
            'goals' => $goals,
            'competencies' => $competencies,
            'overall' => $this->overall($goals, $competencies, (string) $cycle->goals_weight, (string) $cycle->competencies_weight),
        ];
    }

    /**
     * Total weight of the given values (bcmath, 2 decimals).
     *
     * @param  iterable<string|int|float|null>  $weights
     */
    public function totalWeight(iterable $weights): string
    {
        $total = '0';

        foreach ($weights as $weight) {
            $total = bcadd($total, (string) ($weight ?? '0'), 2);
        }

        return $total;
    }

    /**
     * Percentage (0–100, 2 decimals) of a KPI's actual value against its target.
     */
    public function kpiProgress(?string $current, ?string $target): ?string
    {
        if ($current === null || $target === null || bccomp($target, '0', 2) <= 0) {
            return null;
        }

        $percent = bcdiv(bcmul($current, '100', self::SCALE), $target, self::SCALE);

        if (bccomp($percent, '100', self::SCALE) > 0) {
            return '100.00';
        }

        return bccomp($percent, '0', self::SCALE) < 0 ? '0.00' : $this->round($percent);
    }

    private function round(string $value): string
    {
        return bccomp($value, '0', self::SCALE) < 0
            ? bcsub($value, '0.005', 2)
            : bcadd($value, '0.005', 2);
    }
}
