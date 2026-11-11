@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('attendance.batches.title'), 'url' => route('admin.attendance.batches.index')]],
    'subtitle' => __('attendance.import.subtitle'),
])

@section('title', __('attendance.import.title'))

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('attendance.import.step_upload')" class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.attendance.import.analyse') }}" enctype="multipart/form-data" class="flex flex-col gap-4" data-validate>
                @csrf
                <x-form.file-input name="file" :label="__('attendance.import.file')" required accept=".xlsx,.csv" :help="__('attendance.import.file_help', ['max' => config('employees.import_max_rows')])" />
                <div class="flex flex-wrap gap-2">
                    <x-button icon="search">{{ __('attendance.import.dry_run') }}</x-button>
                    <x-button type="button" variant="ghost" icon="download" :href="route('admin.attendance.import.template')">{{ __('attendance.import.template') }}</x-button>
                </div>
            </form>
        </x-card>

        <x-card :title="__('attendance.import.columns')">
            <ul class="flex flex-wrap gap-1.5">
                @foreach ($columns as $column)
                    <li><code class="rounded-xs bg-surface-muted px-1.5 py-0.5 text-caption-md" dir="ltr">{{ $column }}</code></li>
                @endforeach
            </ul>
            <p class="mt-3 text-caption-md text-text-tertiary">{{ __('attendance.import.columns_help') }}</p>
            <p class="mt-2 text-caption-md text-text-tertiary">{{ __('attendance.import.status_codes') }}
                @foreach ($statuses as $status)
                    <code dir="ltr">{{ $status->code }}</code>@if (! $loop->last), @endif
                @endforeach
            </p>
        </x-card>
    </div>

    @if ($report)
        <x-card :title="__('attendance.import.report')" :subtitle="$fileName" data-region="import-report">
            <div class="mb-4 grid gap-3 sm:grid-cols-3">
                <x-stat-card :label="__('attendance.import.rows')" :value="$report['total']" icon="file-text" />
                <x-stat-card :label="__('attendance.import.valid_rows')" :value="$report['valid']" icon="check-circle" />
                <x-stat-card :label="__('attendance.import.errors')" :value="count($report['errors'])" icon="alert-triangle" />
            </div>

            @if ($report['errors'] === [] && $report['valid'] > 0)
                <x-alert tone="success">{{ trans_choice('attendance.import.ready', $report['valid'], ['count' => $report['valid']]) }}</x-alert>
                <form method="POST" action="{{ route('admin.attendance.import.store', $token) }}" class="mt-4">
                    @csrf
                    <input type="hidden" name="file_name" value="{{ $fileName }}">
                    <x-button icon="arrow-right">{{ __('attendance.import.continue') }}</x-button>
                </form>
            @else
                <x-alert tone="danger">{{ __('attendance.import.fix_errors') }}</x-alert>
                <x-table class="mt-4" :caption="__('attendance.import.errors')">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('attendance.import.line') }}</x-table.th>
                            <x-table.th>{{ __('attendance.import.column') }}</x-table.th>
                            <x-table.th>{{ __('attendance.import.message') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($report['errors'] as $error)
                        <x-table.row>
                            <x-table.td :label="__('attendance.import.line')" class="tabular-nums">{{ $error['line'] }}</x-table.td>
                            <x-table.td :label="__('attendance.import.column')"><code dir="ltr">{{ $error['column'] ?: '—' }}</code></x-table.td>
                            <x-table.td :label="__('attendance.import.message')">{{ $error['message'] }}</x-table.td>
                        </x-table.row>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    @endif
@endsection
