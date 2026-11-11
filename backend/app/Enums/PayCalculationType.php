<?php

namespace App\Enums;

/**
 * How a salary component's amount is obtained. A closed list of rule types:
 * there is no formula language and nothing is ever evaluated.
 *
 * - basic:               the employee's basic salary (employee_salaries), one component;
 * - fixed:               a fixed amount per period;
 * - percentage:          rate % of the basic salary or of another component (chains allowed, no cycles);
 * - variable:            no default — the amount is entered on an allowance / deduction entry (commission, bonus);
 * - late_minutes:        attendance late minutes × (fixed amount per minute, or minute rate × multiplier);
 * - early_leave_minutes: attendance early-departure minutes × (amount, or minute rate × multiplier);
 * - absent_days:         attendance absent days × (amount, or day rate × multiplier);
 * - overtime_hours:      overtime hours × hour rate × multiplier (the attendance policy's, or the component's);
 * - unpaid_leave_days:   unpaid leave days (LeavePayrollFeed) × day rate × multiplier;
 * - statutory:           income tax / social insurance from the statutory rule tables.
 */
enum PayCalculationType: string
{
    case Basic = 'basic';
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case Variable = 'variable';
    case LateMinutes = 'late_minutes';
    case EarlyLeaveMinutes = 'early_leave_minutes';
    case AbsentDays = 'absent_days';
    case OvertimeHours = 'overtime_hours';
    case UnpaidLeaveDays = 'unpaid_leave_days';
    case Statutory = 'statutory';

    public function label(): string
    {
        return __('payroll.enums.calculation_types.'.$this->value);
    }

    /**
     * Driven by an attendance or leave quantity of the period.
     */
    public function isQuantityBased(): bool
    {
        return in_array($this, [self::LateMinutes, self::EarlyLeaveMinutes, self::AbsentDays, self::OvertimeHours, self::UnpaidLeaveDays], true);
    }

    /**
     * May be used on an allowance / deduction entry (the entry carries the amount or rate).
     */
    public function allowsEntries(): bool
    {
        return in_array($this, [self::Fixed, self::Percentage, self::Variable], true);
    }

    /**
     * May be a line of a salary structure.
     */
    public function allowsStructureLines(): bool
    {
        return $this !== self::Variable;
    }

    /**
     * The quantity key of App\Services\Payroll\Data\CalculationContext this type reads.
     */
    public function quantityKey(): ?string
    {
        return match ($this) {
            self::LateMinutes => 'late_minutes',
            self::EarlyLeaveMinutes => 'early_leave_minutes',
            self::AbsentDays => 'absent_days',
            self::OvertimeHours => 'overtime_hours',
            self::UnpaidLeaveDays => 'unpaid_leave_days',
            default => null,
        };
    }
}
