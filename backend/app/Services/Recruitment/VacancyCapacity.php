<?php

namespace App\Services\Recruitment;

use App\Enums\VacancyStatus;
use App\Models\Position;
use App\Models\Vacancy;
use App\Services\Employees\PositionCapacity;

/**
 * Openings of a vacancy linked to a position may never exceed the seats the
 * position still has: headcount − occupied seats (PositionCapacity) − the
 * remaining openings of the position's OTHER open / on-hold / draft vacancies.
 */
class VacancyCapacity
{
    public function __construct(private readonly PositionCapacity $capacity) {}

    public function freeSeats(Position $position, ?Vacancy $except = null): int
    {
        $reserved = Vacancy::query()->withoutGlobalScopes()
            ->where('position_id', $position->getKey())
            ->whereIn('status', [VacancyStatus::Draft, VacancyStatus::Open, VacancyStatus::OnHold])
            ->when($except?->exists, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get(['openings', 'filled_count'])
            ->sum(fn (Vacancy $vacancy): int => $vacancy->remainingOpenings());

        return max(0, $position->headcount - $this->capacity->occupied($position) - $reserved);
    }
}
