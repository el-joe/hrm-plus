<?php

namespace App\Services\Attendance\Mobile;

use App\Models\BranchLocation;
use Illuminate\Support\Collection;

/**
 * Where an employee may check in on a date.
 *
 * source: employee | department | branch (the default: active locations of the branch)
 */
final readonly class AllowedLocations
{
    /**
     * @param  Collection<int, BranchLocation>  $locations
     */
    public function __construct(
        public Collection $locations,
        public bool $allowAnywhere,
        public string $source,
    ) {}
}
