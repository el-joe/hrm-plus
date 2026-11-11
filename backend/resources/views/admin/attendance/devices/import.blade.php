@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('devices.index.title'), 'url' => route('admin.attendance-devices.index')], ['label' => $device->name, 'url' => route('admin.attendance-devices.show', $device)]],
    'subtitle' => __('devices.import.subtitle'),
])

@section('title', __('devices.import.title'))

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('devices.import.upload')" class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.attendance-devices.import.analyse', $device) }}" enctype="multipart/form-data" class="flex flex-col gap-4" data-validate>
                @csrf
                <x-form.file-input name="file" :label="__('attendance.import.file')" required accept=".xlsx,.csv" :help="__('devices.import.file_help', ['max' => config('attendance_devices.import_max_rows'), 'timezone' => $device->effectiveTimezone()])" />
                <div class="flex flex-wrap gap-2">
                    <x-button icon="search">{{ __('attendance.import.dry_run') }}</x-button>
                    <x-button type="button" variant="ghost" icon="download" :href="route('admin.attendance-devices.import.template', $device)">{{ __('attendance.import.template') }}</x-button>
                </div>
            </form>
        </x-card>
        <x-card :title="__('attendance.import.columns')">
            <ul class="flex flex-wrap gap-1.5">
                @foreach ($columns as $column)
                    <li><code class="rounded-xs bg-surface-muted px-1.5 py-0.5 text-caption-md" dir="ltr">{{ $column }}</code></li>
                @endforeach
            </ul>
            <p class="mt-3 text-caption-md text-text-tertiary">{{ __('devices.import.columns_help') }}</p>
        </x-card>
    </div>

    @if ($report)
        <x-card :title="__('attendance.import.report')" :subtitle="$fileName" data-region="import-report">
            <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-5">
                <x-stat-card :label="__('attendance.import.rows')" :value="(string) $report['total']" icon="file-text" />
                <x-stat-card :label="__('devices.import.new')" :value="(string) $report['new']" icon="check-circle" />
                <x-stat-card :label="__('devices.logs.unmatched')" :value="(string) $report['unmatched']" icon="users" />
                <x-stat-card :label="__('devices.logs.duplicates')" :value="(string) $report['duplicates']" icon="layers" />
                <x-stat-card :label="__('attendance.import.errors')" :value="(string) count($report['errors'])" icon="alert-triangle" />
            </div>

            @if ($report['errors'] === [] && ($report['new'] + $report['unmatched']) > 0)
                <x-alert tone="success">{{ __('devices.import.ready', ['count' => $report['new'] + $report['unmatched']]) }}</x-alert>
                <form method="POST" action="{{ route('admin.attendance-devices.import.store', [$device, $token]) }}" class="mt-4" data-confirm="{{ __('devices.import.confirm') }}">
                    @csrf
                    <input type="hidden" name="file_name" value="{{ $fileName }}">
                    <x-button icon="upload">{{ __('devices.import.import') }}</x-button>
                </form>
            @elseif ($report['errors'] === [])
                <x-alert tone="info">{{ __('devices.import.nothing_new') }}</x-alert>
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
                            <x-table.td :label="__('attendance.import.column')"><code dir="ltr">{{ $error['column'] }}</code></x-table.td>
                            <x-table.td :label="__('attendance.import.message')">{{ $error['message'] }}</x-table.td>
                        </x-table.row>
                    @endforeach
                </x-table>
            @endif

            @if ($report['preview'] !== [])
                <x-table class="mt-4" :caption="__('devices.import.preview')">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('attendance.import.line') }}</x-table.th>
                            <x-table.th>{{ __('devices.fields.device_user_id') }}</x-table.th>
                            <x-table.th>{{ __('attendance.fields.time') }}</x-table.th>
                            <x-table.th>{{ __('attendance.fields.direction') }}</x-table.th>
                            <x-table.th>{{ __('attendance.fields.status') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @foreach ($report['preview'] as $row)
                        <x-table.row>
                            <x-table.td :label="__('attendance.import.line')" class="tabular-nums">{{ $row['line'] }}</x-table.td>
                            <x-table.td :label="__('devices.fields.device_user_id')"><span dir="ltr">{{ $row['user'] }}</span></x-table.td>
                            <x-table.td :label="__('attendance.fields.time')" class="tabular-nums"><span dir="ltr">{{ $row['time'] }}</span></x-table.td>
                            <x-table.td :label="__('attendance.fields.direction')">{{ $row['direction']->label() }}</x-table.td>
                            <x-table.td :label="__('attendance.fields.status')">
                                <x-badge :tone="match ($row['state']) { 'new' => 'success', 'unmatched' => 'warning', default => 'neutral' }">{{ __('devices.import.states.'.$row['state']) }}</x-badge>
                            </x-table.td>
                        </x-table.row>
                    @endforeach
                </x-table>
            @endif
        </x-card>
    @endif
@endsection
