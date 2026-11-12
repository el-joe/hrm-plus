<?php

namespace App\Services\Payroll;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calendar-day proration of a period: the share (0–1, 6 decimals) of the
 * period's days that fall inside [activeFrom, activeTo]. Used for partial
 * months (hire, termination, mid-period salary change, recurring entries
 * starting or ending inside the period). The factor (6 decimals) is for display and
 * snapshots; amounts are prorated with fraction() (days ÷ period days, divided last).
 */
class Proration
{
    public function factor(CarbonInterface|string $periodStart, CarbonInterface|string $periodEnd, CarbonInterface|string|null $activeFrom, CarbonInterface|string|null $activeTo): string
    {
        $start = CarbonImmutable::parse($periodStart)->startOfDay();
        $end = CarbonImmutable::parse($periodEnd)->startOfDay();
        $periodDays = (int) $start->diffInDays($end) + 1;
        $active = $this->overlapDays($start, $end, $activeFrom, $activeTo);

        if ($periodDays <= 0 || $active <= 0) {
            return '0';
        }

        return $active >= $periodDays ? '1' : bcdiv((string) $active, (string) $periodDays, PayrollMath::SCALE);
    }

    /**
     * The same share as an exact fraction: [active days, period days].
     *
     * @return array{0: int, 1: int}
     */
    public function fraction(CarbonInterface|string $periodStart, CarbonInterface|string $periodEnd, CarbonInterface|string|null $activeFrom, CarbonInterface|string|null $activeTo): array
    {
        $start = CarbonImmutable::parse($periodStart)->startOfDay();
        $end = CarbonImmutable::parse($periodEnd)->startOfDay();
        $periodDays = max(1, (int) $start->diffInDays($end) + 1);

        return [min($periodDays, max(0, $this->overlapDays($start, $end, $activeFrom, $activeTo))), $periodDays];
    }

    public function overlapDays(CarbonInterface $periodStart, CarbonInterface $periodEnd, CarbonInterface|string|null $activeFrom, CarbonInterface|string|null $activeTo): int
    {
        $from = $activeFrom === null ? $periodStart : CarbonImmutable::parse($activeFrom)->startOfDay();
        $to = $activeTo === null ? $periodEnd : CarbonImmutable::parse($activeTo)->startOfDay();
        $from = $from->greaterThan($periodStart) ? $from : $periodStart;
        $to = $to->lessThan($periodEnd) ? $to : $periodEnd;

        return $to->lessThan($from) ? 0 : (int) $from->diffInDays($to) + 1;
    }
}
