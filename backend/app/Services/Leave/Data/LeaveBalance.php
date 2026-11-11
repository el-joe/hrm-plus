<?php

namespace App\Services\Leave\Data;

use App\Models\LeavePolicy;
use App\Models\LeaveType;

/**
 * Balance of one employee for one leave type and leave year, with the
 * breakdown of the ledger. All amounts are bcmath strings (2 decimals);
 * debits (used, expired, encashed, carried out) are shown as positive numbers.
 *
 * balance   = sum of the ledger rows of the year;
 * pending   = days of pending requests (reserved, not yet in the ledger);
 * available = balance - pending.
 */
final readonly class LeaveBalance
{
    public function __construct(
        public LeaveType $type,
        public int $year,
        public ?LeavePolicy $policy,
        public string $entitlement,
        public string $carriedIn,
        public string $accrued,
        public string $adjusted,
        public string $used,
        public string $expired,
        public string $encashed,
        public string $carriedOut,
        public string $balance,
        public string $pending,
        public string $available,
        public string $taken,
    ) {}

    public function tracksBalance(): bool
    {
        return $this->type->tracks_balance;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'leave_type' => ['id' => $this->type->id, 'code' => $this->type->code, 'name' => $this->type->name, 'color' => $this->type->color, 'unit' => $this->type->unit->value],
            'year' => $this->year,
            'tracks_balance' => $this->type->tracks_balance,
            'policy' => $this->policy ? ['id' => $this->policy->id, 'code' => $this->policy->code, 'name' => $this->policy->name] : null,
            'entitlement' => $this->entitlement,
            'carried_in' => $this->carriedIn,
            'accrued' => $this->accrued,
            'adjusted' => $this->adjusted,
            'used' => $this->used,
            'expired' => $this->expired,
            'encashed' => $this->encashed,
            'carried_out' => $this->carriedOut,
            'balance' => $this->balance,
            'pending' => $this->pending,
            'available' => $this->available,
            'taken' => $this->taken,
        ];
    }
}
