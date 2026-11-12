<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Models\PayrollItem;
use App\Services\Reports\Column;

/**
 * Gross salary per employee and period (live pay): basic salary, earnings and gross.
 */
class GrossSalaryReport extends PayrollItemsReport
{
    public function key(): string
    {
        return 'gross_salary';
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'period' => Column::text($this->label('period')),
            'pay_basis' => Column::text($this->label('pay_basis')),
            'basic_salary' => Column::money($this->label('basic_salary'), false),
            'taxable_base' => Column::money($this->label('taxable_base')),
            'gross' => Column::money($this->label('gross')),
        ];
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var PayrollItem $record */
        return [
            ...$this->itemCells($record),
            'pay_basis' => $record->pay_basis?->label(),
            'basic_salary' => $record->getRawOriginal('basic_salary'),
            'taxable_base' => $record->getRawOriginal('taxable_base'),
            'gross' => $record->getRawOriginal('gross_pay'),
        ];
    }
}
