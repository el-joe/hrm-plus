@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('reports.index.title'), 'url' => route('admin.reports.index')], ['label' => __('reports.areas.'.$report->area()), 'url' => route('admin.reports.index').'#area-'.$report->area()]],
    'subtitle' => $report->description(),
])

@section('title', $report->title())

@section('page-actions')
    @if ($canExport)
        <x-dropdown :label="__('reports.exports.export')" data-report-export>
            <x-slot:trigger>
                <span class="inline-flex h-10 items-center gap-2 rounded-md border border-border-default bg-surface-default px-4 text-label-md text-text-primary hover:bg-surface-subtle">
                    <x-icon name="download" size="sm" /> {{ __('reports.exports.export') }} <x-icon name="chevron-down" size="sm" />
                </span>
            </x-slot:trigger>
            @foreach (['xlsx', 'csv', 'pdf'] as $format)
                <x-dropdown.item :href="route('admin.reports.export', [$report->key(), ...request()->except(['page', 'sort', 'direction']), 'format' => $format])" icon="download" data-export-format="{{ $format }}">{{ __('reports.exports.formats.'.$format) }}</x-dropdown.item>
            @endforeach
        </x-dropdown>
    @endif
@endsection

@section('content')
    @if (collect($columns)->contains(fn ($column) => $column->guard === 'disciplinary'))
        <x-alert tone="warning" :title="__('reports.ui.restricted_title')">{{ __('reports.ui.restricted_disciplinary') }}</x-alert>
    @elseif (collect($columns)->contains(fn ($column) => $column->guard === 'financial'))
        <x-alert tone="info">{{ __('reports.ui.restricted_financial') }}</x-alert>
    @endif

    @if (count($siblings) > 1)
        <nav aria-label="{{ __('reports.areas.'.$report->area()) }}" class="relative -mx-1 overflow-x-auto">
            <ul class="flex gap-1 px-1 pb-1">
                @foreach ($siblings as $sibling)
                    <li class="shrink-0">
                        <a href="{{ route('admin.reports.show', [$sibling->key(), ...request()->only(['from', 'to', 'branch_id', 'department_id'])]) }}"
                            @class(['inline-flex h-8 items-center rounded-full px-3 text-label-sm', 'bg-action-primary-default text-text-on-primary' => $sibling->key() === $report->key(), 'bg-surface-default text-text-secondary border border-border-subtle hover:text-text-primary' => $sibling->key() !== $report->key()])
                            @if ($sibling->key() === $report->key()) aria-current="page" @endif>{{ $sibling->title() }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    @include('admin.reports._filters')

    @if ($chart !== null && ! \App\Support\Charts\ChartData::isEmpty($chart))
        <x-card :title="__('reports.ui.chart')" data-section="report-chart">
            <x-chart :chart="$chart" :label="$report->title()" height="18rem" />
        </x-card>
    @endif

    <x-table :caption="$report->title()" data-region="report-table" data-report-table="{{ $report->key() }}">
        <x-slot:head>
            <tr>
                @foreach ($columns as $key => $column)
                    <x-table.th :sort="in_array($key, $sortableKeys, true) ? $key : null" @class(['text-end' => $column->isNumeric()])>{{ $column->label }}</x-table.th>
                @endforeach
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            <x-table.row>
                @foreach ($columns as $key => $column)
                    <x-table.td :label="$column->label" @class(['tabular-nums', 'text-end' => $column->isNumeric(), 'whitespace-nowrap' => $column->isLtr()])>
                        <span @if ($column->isLtr()) dir="ltr" @endif>{{ \App\Services\Reports\ReportExporter::display($column, $row[$key] ?? null) }}</span>
                    </x-table.td>
                @endforeach
            </x-table.row>
        @empty
            <x-table.empty :colspan="count($columns)" icon="file-text" :title="__('reports.ui.empty')" :description="__('reports.ui.empty_help')" />
        @endforelse
        @if ($totals !== null && $rows->total() > 0)
            <tr class="bg-surface-subtle font-semibold" data-report-totals>
                @foreach ($columns as $key => $column)
                    <x-table.td :label="$column->label" @class(['tabular-nums text-label-md', 'text-end' => $column->isNumeric()])>
                        @if (isset($totals[$key]))
                            <span dir="ltr">{{ \App\Services\Reports\ReportExporter::display($column, $totals[$key]) }}</span>
                        @elseif ($loop->first)
                            {{ __('reports.ui.total') }}
                        @endif
                    </x-table.td>
                @endforeach
            </tr>
        @endif
        <x-slot:footer>
            <x-pagination :paginator="$rows" />
        </x-slot:footer>
    </x-table>

    @if ($totals === null && $rows->total() > \App\Services\Reports\ReportRunner::SCREEN_TOTALS_LIMIT)
        <p class="text-caption-md text-text-tertiary">{{ __('reports.ui.totals_in_export') }}</p>
    @endif
@endsection
