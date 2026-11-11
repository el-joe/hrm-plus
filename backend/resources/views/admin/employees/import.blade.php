@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('employees.index.title'), 'url' => route('admin.employees.index')]],
    'subtitle' => __('employees.import.subtitle'),
])

@section('title', __('employees.import.title'))

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('employees.import.step_upload')" class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.employees.import.analyse') }}" enctype="multipart/form-data" class="flex flex-col gap-4" data-validate>
                @csrf
                <x-form.file-input name="file" :label="__('employees.import.file')" required accept=".xlsx,.csv" :help="__('employees.import.file_help', ['max' => config('employees.import_max_rows')])" />
                <div class="flex flex-wrap gap-2">
                    <x-button icon="search">{{ __('employees.import.dry_run') }}</x-button>
                    <x-button type="button" variant="ghost" icon="download" :href="route('admin.employees.import.template')">{{ __('employees.import.template') }}</x-button>
                </div>
            </form>
        </x-card>

        <x-card :title="__('employees.import.columns')">
            <ul class="flex flex-wrap gap-1.5">
                @foreach ($columns as $column)
                    <li><code class="rounded-xs bg-surface-muted px-1.5 py-0.5 text-caption-md" dir="ltr">{{ $column }}</code></li>
                @endforeach
            </ul>
            <p class="mt-3 text-caption-md text-text-tertiary">{{ __('employees.import.columns_help') }}</p>
        </x-card>
    </div>

    @if ($report)
        <x-card :title="__('employees.import.report')" :subtitle="$fileName ?? null" data-region="import-report">
            <div class="mb-4 grid gap-3 sm:grid-cols-3">
                <x-stat-card :label="__('employees.import.rows')" :value="$report['total']" icon="file-text" />
                <x-stat-card :label="__('employees.import.valid_rows')" :value="$report['valid']" icon="check-circle" />
                <x-stat-card :label="__('employees.import.errors')" :value="count($report['errors'])" icon="alert-triangle" />
            </div>

            @if ($report['errors'] === [] && $report['valid'] > 0)
                <x-alert tone="success">{{ trans_choice('employees.import.ready', $report['valid'], ['count' => $report['valid']]) }}</x-alert>
                <form method="POST" action="{{ route('admin.employees.import.store', $token) }}" class="mt-4"
                    data-confirm="{{ trans_choice('employees.import.confirm', $report['valid'], ['count' => $report['valid']]) }}" data-confirm-tone="info"
                    data-confirm-label="{{ __('employees.import.run') }}">
                    @csrf
                    <x-button icon="upload">{{ __('employees.import.run') }}</x-button>
                </form>
            @else
                <x-alert tone="danger">{{ __('employees.import.fix_errors') }}</x-alert>
                <x-table class="mt-4" :caption="__('employees.import.errors')">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('employees.import.line') }}</x-table.th>
                            <x-table.th>{{ __('employees.import.column') }}</x-table.th>
                            <x-table.th>{{ __('employees.import.message') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($report['errors'] as $error)
                        <x-table.row>
                            <x-table.td :label="__('employees.import.line')" class="tabular-nums">{{ $error['line'] }}</x-table.td>
                            <x-table.td :label="__('employees.import.column')"><code dir="ltr">{{ $error['column'] ?: '—' }}</code></x-table.td>
                            <x-table.td :label="__('employees.import.message')">{{ $error['message'] }}</x-table.td>
                        </x-table.row>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    @endif
@endsection
