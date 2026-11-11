<?php

namespace App\Services\Leave\Data;

/**
 * The days of a requested leave period and the total charged.
 */
final readonly class LeaveCount
{
    /**
     * @param  list<array{date: string, leave_year: int, day_part: string, amount: string, hours: ?string, is_counted: bool, day_kind: string}>  $days
     * @param  string  $total  days charged (bcmath string, 2 decimals)
     * @param  array<int, string>  $byYear  leave year => days charged in it
     */
    public function __construct(
        public array $days,
        public string $total,
        public array $byYear,
    ) {}

    public function isEmpty(): bool
    {
        return bccomp($this->total, '0', 2) <= 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['total' => $this->total, 'by_year' => $this->byYear, 'days' => $this->days];
    }
}
