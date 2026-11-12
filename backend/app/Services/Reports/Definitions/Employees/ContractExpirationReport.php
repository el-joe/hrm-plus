<?php

namespace App\Services\Reports\Definitions\Employees;

use App\Enums\EmployeeStatus;
use App\Models\EmployeeContract;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Contracts ending in the period (default: the next 90 days) that were neither terminated nor
 * renewed, for employees still employed.
 */
class ContractExpirationReport extends Report
{
    public function key(): string
    {
        return 'contract_expiration';
    }

    public function area(): string
    {
        return 'employees';
    }

    public function filters(): array
    {
        return array_values(array_diff(ReportFilters::EMPLOYEE_FILTERS, ['employee_status']));
    }

    public function defaults(): array
    {
        $today = CarbonImmutable::today();

        return ['from' => $today->format('Y-m-d'), 'to' => $today->addDays(90)->format('Y-m-d')];
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'contract_type' => Column::text($this->label('contract_type')),
            'reference' => Column::code($this->label('reference')),
            'start_date' => Column::date($this->label('start_date')),
            'end_date' => Column::date($this->label('end_date')),
            'days_left' => Column::number($this->label('days_left'), false),
        ];
    }

    public function sortable(): array
    {
        return ['end_date' => 'employee_contracts.end_date', 'days_left' => 'employee_contracts.end_date', 'start_date' => 'employee_contracts.start_date'];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = EmployeeContract::query()
            ->with(['employee.branch', 'employee.department'])
            ->whereBetween('employee_contracts.end_date', [$filters['from'], $filters['to']])
            ->whereNull('employee_contracts.terminated_on')
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('employee_contracts as renewals')->whereColumn('renewals.renewal_of_id', 'employee_contracts.id'))
            ->whereHas('employee', fn (Builder $query) => $query->where('status', '!=', EmployeeStatus::Terminated->value))
            ->orderBy('employee_contracts.end_date')->orderBy('employee_contracts.id');

        return ReportFilters::applyEmployeeFilters($query, 'employee_contracts.employee_id', $filters);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var EmployeeContract $record */
        return [
            ...$this->employeeCells($record->employee),
            'contract_type' => $record->contract_type?->label(),
            'reference' => $record->reference,
            'start_date' => $record->start_date,
            'end_date' => $record->end_date,
            'days_left' => $record->end_date ? (int) CarbonImmutable::today()->diffInDays($record->end_date, false) : null,
        ];
    }
}
