<?php

namespace App\Services\Leave;

use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Leave years start on the first day of the month in setting
 * leave.year_start_month. A leave year is named after the calendar year it
 * starts in (start month July: leave year 2026 = 2026-07-01 … 2027-06-30).
 */
class LeaveYear
{
    public function __construct(private readonly SettingsService $settings) {}

    public function startMonth(): int
    {
        return max(1, min(12, (int) $this->settings->get('leave.year_start_month')));
    }

    public function of(DateTimeInterface|string $date): int
    {
        $date = CarbonImmutable::parse($date instanceof DateTimeInterface ? $date->format('Y-m-d') : $date);

        return $date->month >= $this->startMonth() ? $date->year : $date->year - 1;
    }

    public function start(int $year): CarbonImmutable
    {
        return CarbonImmutable::create($year, $this->startMonth(), 1)->startOfDay();
    }

    public function end(int $year): CarbonImmutable
    {
        return $this->start($year + 1)->subDay();
    }

    public function current(): int
    {
        return $this->of(now()->toDateString());
    }

    /**
     * "2026" or "2026/27" when the leave year does not start in January.
     */
    public function label(int $year): string
    {
        return $this->startMonth() === 1 ? (string) $year : $year.'/'.substr((string) ($year + 1), -2);
    }
}
