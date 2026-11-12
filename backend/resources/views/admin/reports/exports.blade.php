@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('reports.index.title'), 'url' => route('admin.reports.index')]],
    'subtitle' => __('reports.exports.subtitle'),
])

@section('title', __('reports.exports.title'))

@section('content')
    <x-table :caption="__('reports.exports.title')" data-region="report-exports">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('reports.exports.report') }}</x-table.th>
                <x-table.th>{{ __('reports.exports.format') }}</x-table.th>
                <x-table.th>{{ __('reports.exports.requested_at') }}</x-table.th>
                <x-table.th>{{ __('reports.exports.status') }}</x-table.th>
                <x-table.th class="text-end">{{ __('reports.exports.rows') }}</x-table.th>
                <x-table.th>{{ __('reports.exports.expires_at') }}</x-table.th>
                <x-table.th><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($exports as $export)
            <x-table.row data-export="{{ $export->id }}">
                <x-table.td :label="__('reports.exports.report')">{{ $registry->find($export->report)?->title() ?? $export->report }}</x-table.td>
                <x-table.td :label="__('reports.exports.format')"><code>{{ strtoupper($export->format) }}</code></x-table.td>
                <x-table.td :label="__('reports.exports.requested_at')"><x-date :value="$export->created_at" format="datetime" /></x-table.td>
                <x-table.td :label="__('reports.exports.status')">
                    <x-badge :tone="$export->status->tone()" dot>{{ $export->status->label() }}</x-badge>
                    @if ($export->error)
                        <p class="mt-1 text-caption-md text-feedback-danger-text">{{ $export->error }}</p>
                    @endif
                </x-table.td>
                <x-table.td :label="__('reports.exports.rows')" class="text-end"><x-number :value="$export->rows" /></x-table.td>
                <x-table.td :label="__('reports.exports.expires_at')"><x-date :value="$export->expires_at" format="datetime" /></x-table.td>
                <x-table.td :label="__('app.common.actions')" class="text-end">
                    @if ($export->isDownloadable())
                        <x-button variant="secondary" size="sm" icon="download" :href="route('admin.reports.exports.download', $export)">{{ __('reports.exports.download') }}</x-button>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="download" :title="__('reports.exports.empty')" :description="__('reports.exports.empty_help')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$exports" />
        </x-slot:footer>
    </x-table>
@endsection
