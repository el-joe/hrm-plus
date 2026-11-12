<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Enums\PayrollAdjustmentStatus;
use App\Models\PayrollAdjustment;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Post-finalisation payroll adjustments (corrections) created in the period: source (attendance,
 * leave, manual), component, signed amount, state and the period they correct.
 */
class PayrollAdjustmentsReport extends PayrollReport
{
    public function key(): string
    {
        return 'payroll_adjustments';
    }

    public function extraFilters(): array
    {
        return ['adjustment_status' => [
            'label' => __('reports.filters.request_status'),
            'options' => collect(PayrollAdjustmentStatus::cases())->mapWithKeys(fn (PayrollAdjustmentStatus $status): array => [$status->value => $status->label()])->all(),
        ]];
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'reference' => Column::code($this->label('reference')),
            'created_at' => Column::dateTime($this->label('created_at')),
            'source' => Column::text($this->label('source')),
            'component' => Column::text($this->label('component')),
            'kind' => Column::text($this->label('kind')),
            'amount' => Column::money($this->label('amount')),
            'original_period' => Column::text($this->label('original_period')),
            'status' => Column::text($this->label('status')),
            'reason' => Column::text($this->label('reason')),
        ];
    }

    public function sortable(): array
    {
        return ['created_at' => 'payroll_adjustments.created_at', 'amount' => 'payroll_adjustments.amount', 'status' => 'payroll_adjustments.status'];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = PayrollAdjustment::query()
            ->with(['employee.branch', 'employee.department', 'originalPeriod'])
            ->whereBetween('payroll_adjustments.created_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])
            ->when($filters['adjustment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payroll_adjustments.status', $status))
            ->orderBy('payroll_adjustments.created_at')->orderBy('payroll_adjustments.id');

        return ReportFilters::applyEmployeeFilters($query, 'payroll_adjustments.employee_id', $filters);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var PayrollAdjustment $record */
        return [
            ...$this->employeeCells($record->employee),
            'reference' => $record->reference,
            'created_at' => $record->created_at,
            'source' => $record->source?->label(),
            'component' => trim($record->code.' · '.(app()->getLocale() === 'en' ? $record->name_en : $record->name_ar), ' ·'),
            'kind' => $record->kind?->label(),
            'amount' => (string) $record->getRawOriginal('amount'),
            'original_period' => $record->originalPeriod?->name,
            'status' => $record->status?->label(),
            'reason' => $record->reason ?? $record->dismiss_reason,
        ];
    }
}
