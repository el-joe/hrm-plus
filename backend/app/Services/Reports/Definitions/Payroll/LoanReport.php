<?php

namespace App\Services\Reports\Definitions\Payroll;

use App\Enums\LoanInstalmentStatus;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\EmployeeLoan;
use App\Models\LoanInstalment;
use App\Models\User;
use App\Services\Reports\Column;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * Loans or advances (employee_loans, data-scoped through the employee): approved amount,
 * recovered, outstanding, instalments left and the next due date (from loan_instalments).
 */
abstract class LoanReport extends PayrollReport
{
    abstract protected function type(): LoanType;

    public function extraFilters(): array
    {
        return ['loan_status' => [
            'label' => __('reports.filters.loan_status'),
            'options' => collect(LoanStatus::cases())->mapWithKeys(fn (LoanStatus $status): array => [$status->value => $status->label()])->all(),
        ]];
    }

    public function columns(): array
    {
        return [
            ...$this->employeeColumns(),
            'reference' => Column::code($this->label('reference')),
            'start_date' => Column::date($this->label('start_date')),
            'status' => Column::text($this->label('status')),
            'approved' => Column::money($this->label('approved_amount')),
            'recovered' => Column::money($this->label('recovered')),
            'outstanding' => Column::money($this->label('outstanding')),
            'instalments_left' => Column::number($this->label('instalments_left')),
            'next_due' => Column::date($this->label('next_due')),
        ];
    }

    public function sortable(): array
    {
        return ['start_date' => 'employee_loans.start_date', 'outstanding' => 'employee_loans.outstanding_amount', 'approved' => 'employee_loans.approved_amount', 'reference' => 'employee_loans.reference'];
    }

    public function query(array $filters, User $user): Builder
    {
        $open = [LoanInstalmentStatus::Scheduled->value];
        $query = EmployeeLoan::query()
            ->with(['employee.branch', 'employee.department'])
            ->where('employee_loans.type', $this->type()->value)
            ->where(fn (Builder $query) => $query
                ->whereBetween('employee_loans.start_date', [$filters['from'], $filters['to']])
                ->orWhere(fn (Builder $query) => $query->where('employee_loans.status', LoanStatus::Active->value)->where('employee_loans.start_date', '<', $filters['from'])))
            ->when($filters['loan_status'] ?? null, fn (Builder $query, string $status) => $query->where('employee_loans.status', $status), fn (Builder $query) => $query->whereIn('employee_loans.status', [LoanStatus::Active->value, LoanStatus::Completed->value]))
            ->select('employee_loans.*')
            ->addSelect([
                'instalments_left' => LoanInstalment::query()->selectRaw('count(*)')->whereColumn('loan_instalments.employee_loan_id', 'employee_loans.id')->whereIn('status', $open),
                'next_due' => LoanInstalment::query()->selectRaw('min(due_date)')->whereColumn('loan_instalments.employee_loan_id', 'employee_loans.id')->whereIn('status', $open),
            ])
            ->orderBy('employee_loans.start_date')->orderBy('employee_loans.id');

        return ReportFilters::applyEmployeeFilters($query, 'employee_loans.employee_id', $filters);
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var EmployeeLoan $record */
        $approved = (string) ($record->getRawOriginal('approved_amount') ?? '0');
        $outstanding = (string) ($record->getRawOriginal('outstanding_amount') ?? '0');

        return [
            ...$this->employeeCells($record->employee),
            'reference' => $record->reference,
            'start_date' => $record->start_date,
            'status' => $record->status?->label(),
            'approved' => $approved,
            'recovered' => bcsub($approved, $outstanding, 2),
            'outstanding' => $outstanding,
            'instalments_left' => (int) $record->getAttribute('instalments_left'),
            'next_due' => $record->getAttribute('next_due'),
        ];
    }
}
