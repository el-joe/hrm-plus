<?php

namespace App\Services\Payroll;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Calendar-day proration of a period: the share (0–1, 6 decimals) of the
 * period's days that fall inside [activeFrom, activeTo]. Used for partial
 * months (hire, termination, mid-period salary change, recurring entries
 * starting or ending inside the period).
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

    public function overlapDays(CarbonInterface $periodStart, CarbonInterface $periodEnd, CarbonInterface|string|null $activeFrom, CarbonInterface|string|null $activeTo): int
    {
        $from = $activeFrom === null ? $periodStart : CarbonImmutable::parse($activeFrom)->startOfDay();
        $to = $activeTo === null ? $periodEnd : CarbonImmutable::parse($activeTo)->startOfDay();
        $from = $from->greaterThan($periodStart) ? $from : $periodStart;
        $to = $to->lessThan($periodEnd) ? $to : $periodEnd;

        return $to->lessThan($from) ? 0 : (int) $from->diffInDays($to) + 1;
    }
}
