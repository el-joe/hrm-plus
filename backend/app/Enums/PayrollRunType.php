<?php

namespace App\Enums;

/**
 * Kind of payroll run (settlement):
 *
 * - regular: the period's pay of its population (holds the employee's pay slot);
 * - correction: an off-cycle run carrying pending post-finalisation adjustments (detected or
 *   manual) of its employees, no pay slot;
 * - final_settlement: the leaver's last pay (prorated salary, leave encashment, loan recovery), holds the pay slot;
 * - reversal: the mirror of a reversed finalised run (negative amounts), created by the reversal itself.
 */
enum PayrollRunType: string
{
    case Regular = 'regular';
    case Correction = 'correction';
    case FinalSettlement = 'final_settlement';
    case Reversal = 'reversal';

    public function label(): string
    {
        return __('payroll_runs.enums.run_types.'.$this->value);
    }

    /**
     * Whether its items hold the employee's pay for the period (double-pay prevention).
     */
    public function holdsPaySlot(): bool
    {
        return in_array($this, [self::Regular, self::FinalSettlement], true);
    }

    /**
     * Whether the base salary, structure, attendance and leave are calculated (versus adjustment / entry lines only).
     */
    public function paysSalary(): bool
    {
        return in_array($this, [self::Regular, self::FinalSettlement], true);
    }

    /**
     * Types an administrator creates from the wizard.
     *
     * @return list<self>
     */
    public static function creatable(): array
    {
        return [self::Regular, self::Correction];
    }
}
