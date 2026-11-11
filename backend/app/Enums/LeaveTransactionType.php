<?php

namespace App\Enums;

/**
 * Kind of a leave balance ledger row (signed amount: credits positive).
 */
enum LeaveTransactionType: string
{
    case Accrual = 'accrual';
    case Usage = 'usage';
    case Reversal = 'reversal';
    case Adjustment = 'adjustment';
    case CarryOver = 'carry_over';
    case Expiry = 'expiry';
    case Encashment = 'encashment';

    public function label(): string
    {
        return __('leave.enums.transaction_types.'.$this->value);
    }

    /**
     * Kinds an admin may record by hand.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Adjustment, self::Encashment];
    }
}
