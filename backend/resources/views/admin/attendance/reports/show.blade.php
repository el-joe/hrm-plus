@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('attendance_reports.index.title'), 'url' => route('admin.attendance.reports.index')]],
    'subtitle' => __('attendance_reports.reports.'.$report.'.description'),
])

@section('title', __('attendance_reports.reports.'.$report.'.title'))

@section('page-actions')
    @can('exportAttendanceReports')
        <x-button variant="secondary" icon="download" :href="route('admin.attendance.reports.export', [$report, ...request()->except('page'), 'format' => 'xlsx'])">{{ __('attendance_reports.export_xlsx') }}</x-button>
        <x-button variant="secondary" icon="download" :href="route('admin.attendance.reports.export', [$report, ...request()->except('page'), 'format' => 'csv'])">{{ __('attendance_reports.export_csv') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-tabs :items="collect($reports)->map(fn ($key) => ['label' => __('attendance_reports.reports.'.$key.'.title'), 'url' => route('admin.attendance.reports.show', [$key, ...request()->only(['from', 'to', 'branch_id', 'department_id'])]), 'active' => $key === $report])->all()" />

    <x-filter-bar :action="route('admin.attendance.reports.show', $report)" :reset="route('admin.attendance.reports.show', $report)">
        <x-form.input name="from" type="date" :label="__('attendance.fields.from')" hide-label :value="$filters['from']" class="md:w-40" />
        <x-form.input name="to" type="date" :label="__('attendance.fields.to')" hide-label :value="$filters['to']" class="md:w-40" />
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="$report === 'device_sync' ? __('devices.index.search') : __('attendance.common.search_employee')" :value="$filters['search'] ?? ''" class="md:w-52" data-filter-debounce />
        <x-form.select name="branch_id" :label="__('attendance.fields.branch')" hide-label class="md:w-44">
            <option value="">{{ __('attendance.fields.branch') }}: {{ __('app.common.all') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        @if ($report !== 'device_sync')
            <x-form.select name="department_id" :label="__('attendance.fields.department')" hide-label class="md:w-44">
                <option value="">{{ __('attendance.fields.department') }}: {{ __('app.common.all') }}</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
                @endforeach
            </x-form.select>
        @endif
    </x-filter-bar>

    <x-table :caption="__('attendance_reports.reports.'.$report.'.title')" data-region="report-table">
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
                        @if ($key === 'simulated' && $row[$key])
                            @include('admin.attendance._simulated')
                        @else
                            <span @if (in_array($key, ['date', 'check_in', 'check_out', 'worked', 'late', 'early_leave', 'overtime', 'overtime_approved', 'scheduled_start', 'started_at', 'recorded_at', 'employee_number', 'serial'], true)) dir="ltr" @endif>{{ $row[$key] ?? '—' }}</span>
                        @endif
                    </x-table.td>
                @endforeach
            </x-table.row>
        @empty
            <x-table.empty :colspan="count($columns)" icon="file-text" :title="__('attendance_reports.empty')" :description="__('attendance_reports.empty_help')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$rows" />
        </x-slot:footer>
    </x-table>
@endsection
