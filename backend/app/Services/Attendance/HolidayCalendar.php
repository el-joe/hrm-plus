<?php

namespace App\Services\Attendance;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Public holidays by date and branch (company-wide ones apply to every branch).
 */
class HolidayCalendar
{
    /** @var Collection<int, Holiday>|null */
    private ?Collection $holidays = null;

    public function holidayOn(CarbonImmutable $date, ?int $branchId): ?Holiday
    {
        return $this->all()
            ->filter(fn (Holiday $holiday): bool => $holiday->branch_id === null || $holiday->branch_id === $branchId)
            ->sortBy(fn (Holiday $holiday): int => $holiday->branch_id === null ? 1 : 0)
            ->first(fn (Holiday $holiday): bool => $holiday->covers($date));
    }

    /**
     * Holidays touching a date range, for calendars: date (Y-m-d) => holiday.
     *
     * @return array<string, Holiday>
     */
    public function between(CarbonImmutable $from, CarbonImmutable $to, ?int $branchId): array
    {
        $days = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            if ($holiday = $this->holidayOn($date, $branchId)) {
                $days[$date->format('Y-m-d')] = $holiday;
            }
        }

        return $days;
    }

    public function forget(): void
    {
        $this->holidays = null;
    }

    /**
     * @return Collection<int, Holiday>
     */
    private function all(): Collection
    {
        return $this->holidays ??= Holiday::query()->active()->orderBy('start_date')->orderBy('id')->get();
    }
}
