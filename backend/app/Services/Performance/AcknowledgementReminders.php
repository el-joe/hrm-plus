<?php

namespace App\Services\Performance;

use App\Enums\PerformanceReviewStatus;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Notifications\Performance\PerformanceReviewNotification;
use Carbon\CarbonImmutable;

/**
 * Reminds employees of approved reviews they have not acknowledged: on every
 * multiple of setting performance.acknowledgement_days after approval (day 14,
 * 28, ...), so a daily run sends each reminder once without extra state.
 */
class AcknowledgementReminders
{
    public function send(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $every = max(1, (int) setting('performance.acknowledgement_days'));
        $sent = 0;

        $reviews = PerformanceReview::query()->withoutGlobalScopes()
            ->where('status', PerformanceReviewStatus::Acknowledgement)
            ->whereNotNull('approved_at')
            ->whereDate('approved_at', '<=', $today->subDays($every)->toDateString())
            ->get();

        foreach ($reviews as $review) {
            $days = (int) CarbonImmutable::parse($review->approved_at->toDateString())->diffInDays($today);

            if ($days === 0 || $days % $every !== 0) {
                continue;
            }

            foreach (User::query()->where('employee_id', $review->employee_id)->get() as $user) {
                $user->notify(new PerformanceReviewNotification($review, 'acknowledgement_reminder'));
                $sent++;
            }
        }

        return $sent;
    }
}
