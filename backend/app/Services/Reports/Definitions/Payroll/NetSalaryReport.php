<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Models\PayrollItem;
use App\Services\Reports\Column;

/**
 * Net salary per employee and period (live pay) with the payslip payment status.
 */
class NetSalaryReport extends PayrollItemsReport
{
    public function key(): string
    {
        return 'net_salary';
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'period' => Column::text($this->label('period')),
            'gross' => Column::money($this->label('gross')),
            'deductions' => Column::money($this->label('deductions')),
            'net' => Column::money($this->label('net')),
            'payslip' => Column::code($this->label('payslip')),
            'payment_status' => Column::text($this->label('payment_status')),
        ];
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var PayrollItem $record */
        return [
            ...$this->itemCells($record),
            'gross' => $record->getRawOriginal('gross_pay'),
            'deductions' => $record->getRawOriginal('total_deductions'),
            'net' => $record->getRawOriginal('net_pay'),
            'payslip' => $record->payslip?->number,
            'payment_status' => $record->payslip?->payment_status?->label(),
        ];
    }
}
