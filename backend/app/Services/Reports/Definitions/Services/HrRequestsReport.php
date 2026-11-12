<?php

namespace App\Services\Reports\Definitions\Services;

use App\Enums\HrRequestStatus;
use App\Models\HrRequest;
use App\Models\HrRequestCategory;
use App\Models\User;
use App\Services\HrRequests\HrRequestVisibility;
use App\Services\Reports\Column;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

/**
 * HR requests opened in the period (spec module 13; beyond the module 16 list): category, priority,
 * status, assignee, due date, SLA met / overdue, first response time. Data-scoped through the
 * employee; admins with hr_requests.view but not manage see only what HrRequestVisibility allows.
 */
class HrRequestsReport extends Report
{
    public function key(): string
    {
        return 'hr_requests';
    }

    public function area(): string
    {
        return 'requests';
    }

    public function extraFilters(): array
    {
        return [
            'category_id' => ['label' => __('reports.filters.category'), 'options' => HrRequestCategory::query()->orderBy('sort_order')->get()->mapWithKeys(fn (HrRequestCategory $category): array => [$category->id => $category->name])->all()],
            'request_status' => ['label' => __('reports.filters.request_status'), 'options' => collect(HrRequestStatus::cases())->mapWithKeys(fn (HrRequestStatus $status): array => [$status->value => $status->label()])->all()],
        ];
    }

    public function columns(): array
    {
        return [
            'reference' => Column::code($this->label('reference')),
            'created_at' => Column::dateTime($this->label('created_at')),
            ...$this->employeeColumns(),
            'category' => Column::text($this->label('category')),
            'priority' => Column::text($this->label('priority')),
            'status' => Column::text($this->label('status')),
            'assignee' => Column::text($this->label('assignee')),
            'due_at' => Column::dateTime($this->label('due_at')),
            'sla' => Column::text($this->label('sla')),
            'first_response_hours' => Column::decimal($this->label('first_response_hours'), 1, false),
        ];
    }

    public function sortable(): array
    {
        return ['created_at' => 'hr_requests.created_at', 'due_at' => 'hr_requests.due_at', 'status' => 'hr_requests.status', 'reference' => 'hr_requests.reference'];
    }

    public function query(array $filters, User $user): Builder
    {
        $query = HrRequest::query()
            ->with(['employee', 'branch', 'department', 'category', 'assignee'])
            ->whereBetween('hr_requests.created_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->where('hr_requests.hr_request_category_id', $id))
            ->when($filters['request_status'] ?? null, fn (Builder $query, string $status) => $query->where('hr_requests.status', $status))
            ->orderBy('hr_requests.created_at')->orderBy('hr_requests.id');
        $query = ReportFilters::applyEmployeeFilters($query, 'hr_requests.employee_id', $filters, ['branch' => 'hr_requests.branch_id', 'department' => 'hr_requests.department_id']);

        if ($user->can('hr_requests.view') && ! $user->can('hr_requests.manage')) {
            app(HrRequestVisibility::class)->constrain($query, $user);
        }

        return $query;
    }

    public function row(mixed $record, array $filters): array
    {
        /** @var HrRequest $record */
        $closed = in_array($record->status, [HrRequestStatus::Resolved, HrRequestStatus::Rejected, HrRequestStatus::Cancelled], true);
        $sla = match (true) {
            $record->due_at === null => null,
            $record->resolved_at !== null => $record->resolved_at->lte($record->due_at) ? __('reports.ui.sla_met') : __('reports.ui.sla_missed'),
            ! $closed && $record->status !== HrRequestStatus::AwaitingEmployee && $record->due_at->isPast() => __('reports.ui.overdue'),
            default => null,
        };

        return [
            'reference' => $record->reference,
            'created_at' => $record->created_at,
            'employee_number' => $record->employee?->employee_number,
            'employee' => $record->employee?->name,
            'branch' => $record->branch?->name,
            'department' => $record->department?->name,
            'category' => $record->category?->name,
            'priority' => $record->priority?->label(),
            'status' => $record->status?->label(),
            'assignee' => $record->assignee?->name,
            'due_at' => $record->due_at,
            'sla' => $sla,
            'first_response_hours' => $record->first_response_at ? number_format($record->created_at->diffInMinutes($record->first_response_at) / 60, 1, '.', '') : null,
        ];
    }
}
