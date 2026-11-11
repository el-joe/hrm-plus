@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('leave.reports.title'), 'url' => route('admin.leave-reports.index')]],
    'subtitle' => __('leave.reports.list.'.$report.'.description'),
])

@section('title', __('leave.reports.list.'.$report.'.title'))

@section('page-actions')
    @if ($canExport)
        <x-button variant="secondary" icon="download" :href="route('admin.leave-reports.export', [$report, ...request()->except('page'), 'format' => 'xlsx'])">{{ __('leave.reports.export_xlsx') }}</x-button>
        <x-button variant="secondary" icon="download" :href="route('admin.leave-reports.export', [$report, ...request()->except('page'), 'format' => 'csv'])">{{ __('leave.reports.export_csv') }}</x-button>
    @endif
@endsection

@section('content')
    <x-tabs :items="collect($reports)->map(fn ($key) => ['label' => __('leave.reports.list.'.$key.'.title'), 'url' => route('admin.leave-reports.show', [$key, ...request()->only(['branch_id', 'department_id', 'leave_type_id'])]), 'active' => $key === $report])->all()" />

    <x-filter-bar :action="route('admin.leave-reports.show', $report)" :reset="route('admin.leave-reports.show', $report)">
        @if ($report === 'balances')
            <x-form.input name="year" type="number" :label="__('leave.fields.leave_year')" hide-label :value="$filters['year']" min="2000" max="2100" class="md:w-28" />
        @elseif ($report === 'on_leave')
            <x-form.input name="date" type="date" :label="__('leave.fields.date')" hide-label :value="$filters['date']" class="md:w-40" />
        @else
            <x-form.input name="from" type="date" :label="__('leave.fields.from')" hide-label :value="$filters['from']" class="md:w-40" />
            <x-form.input name="to" type="date" :label="__('leave.fields.to')" hide-label :value="$filters['to']" class="md:w-40" />
        @endif
        @if (in_array($report, ['balances', 'requests', 'upcoming', 'on_leave'], true))
            <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('leave.common.search_employee')" :value="$filters['search'] ?? ''" class="md:w-48" data-filter-debounce />
        @endif
        @if ($report !== 'balances')
            <x-form.select name="leave_type_id" :label="__('leave.fields.leave_type_id')" hide-label class="md:w-40">
                <option value="">{{ __('leave.fields.leave_type_id') }}: {{ __('app.common.all') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->id }}" @selected((string) ($filters['leave_type_id'] ?? '') === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
            </x-form.select>
        @endif
        @if ($report === 'requests')
            <x-form.select name="status" :label="__('leave.fields.status')" hide-label class="md:w-40">
                <option value="">{{ __('leave.fields.status') }}: {{ __('app.common.all') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-form.select>
        @endif
        <x-form.select name="branch_id" :label="__('leave.fields.branch')" hide-label class="md:w-40">
            <option value="">{{ __('leave.fields.branch') }}: {{ __('app.common.all') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="department_id" :label="__('leave.fields.department')" hide-label class="md:w-44">
            <option value="">{{ __('leave.fields.department') }}: {{ __('app.common.all') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('leave.reports.list.'.$report.'.title')" data-region="report-table">
        <x-slot:head>
            <tr>
                @foreach ($columns as $key => $label)
                    <x-table.th @class(['text-end' => in_array($key, $numeric, true)])>{{ $label }}</x-table.th>
                @endforeach
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <x-table.row>
                @foreach ($columns as $key => $label)
                    <x-table.td :label="$label" @class(['tabular-nums', 'text-end' => in_array($key, $numeric, true)])>
                        <span @if (in_array($key, ['employee_number', 'start_date', 'end_date', 'requested_at', 'decided_at'], true)) dir="ltr" @endif>{{ $row[$key] ?? '—' }}</span>
                    </x-table.td>
                @endforeach
            </x-table.row>
        @empty
            <x-table.empty :colspan="count($columns)" icon="file-text" :title="__('leave.reports.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$rows" />
        </x-slot:footer>
    </x-table>
@endsection
