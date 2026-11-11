<?php

namespace App\Services\Payroll\Data;

use App\Enums\PayEntryRecurrence;
use App\Enums\PayEntryTarget;

/**
 * An approved allowance / deduction entry that applies to the employee in
 * the period, after scope precedence. rule carries the entry's amount
 * (fixed: amount; percentage: rate + baseCode) and payroll treatment.
 *
 * activeFrom / activeTo: the part of the period the entry covers (for proration
 * of recurring entries). Instalment entries carry instalmentNumber / instalmentCount
 * and the instalment amount as rule->amount.
 */
final readonly class PayEntryInput
{
    public function __construct(
        public int $entryId,
        public string $reference,
        public ComponentRule $rule,
        public PayEntryTarget $target,
        public PayEntryRecurrence $recurrence,
        public string $activeFrom,
        public string $activeTo,
        public ?int $instalmentNumber = null,
        public ?int $instalmentCount = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entry_id' => $this->entryId,
            'reference' => $this->reference,
            'rule' => $this->rule->toArray(),
            'target' => $this->target->value,
            'recurrence' => $this->recurrence->value,
            'active_from' => $this->activeFrom,
            'active_to' => $this->activeTo,
            'instalment_number' => $this->instalmentNumber,
            'instalment_count' => $this->instalmentCount,
        ];
    }
}
