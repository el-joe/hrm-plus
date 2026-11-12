<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Enums\PayrollRunType;
use App\Models\PayrollItem;
use App\Services\Reports\Column;

/**
 * Payroll history: every pay of every finalised run (regular, correction, final settlement,
 * reversal — reversals carry negative amounts) per employee and period.
 */
class PayrollHistoryReport extends PayrollItemsReport
{
    public function key(): string
    {
        return 'payroll_history';
    }

    protected function liveOnly(): bool
    {
        return false;
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'period' => Column::text($this->label('period')),
            'run' => Column::code($this->label('run')),
            'run_type' => Column::text($this->label('run_type')),
            'gross' => Column::money($this->label('gross')),
            'deductions' => Column::money($this->label('deductions')),
            'net' => Column::money($this->label('net')),
        ];
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var PayrollItem $record */
        return [
            ...$this->itemCells($record),
            'run' => $record->getAttribute('run_reference'),
            'run_type' => PayrollRunType::tryFrom((string) $record->getAttribute('run_type'))?->label(),
            'gross' => $record->getRawOriginal('gross_pay'),
            'deductions' => $record->getRawOriginal('total_deductions'),
            'net' => $record->getRawOriginal('net_pay'),
        ];
    }
}
