@php use App\Services\Attendance\AttendanceClock; use App\Support\Duration; @endphp
@extends('layouts.admin', ['subtitle' => __('attendance.daily.subtitle')])

@section('title', __('attendance.daily.title'))

@section('page-actions')
    @can('attendance.export')
        <x-button variant="secondary" icon="download" :href="route('admin.attendance.daily.export', request()->query())">{{ __('attendance.common.export') }}</x-button>
    @endcan
    @can('attendance.create')
        <x-button icon="check-circle" :href="route('admin.attendance.bulk.create', ['date' => $filters['date']])">{{ __('attendance.bulk.title') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.attendance.daily')" :reset="route('admin.attendance.daily')" data-region="filters">
        <x-form.input name="date" type="date" :label="__('attendance.fields.date')" hide-label :value="$filters['date']" class="md:w-44" />
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('attendance.common.search_employee')" :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="branch_id" :label="__('attendance.fields.branch')" hide-label class="md:w-48">
            <option value="">{{ __('attendance.fields.branch') }}: {{ __('app.common.all') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="department_id" :label="__('attendance.fields.department')" hide-label class="md:w-48">
            <option value="">{{ __('attendance.fields.department') }}: {{ __('app.common.all') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('attendance.fields.status')" hide-label class="md:w-40">
            <option value="">{{ __('attendance.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="source" :label="__('attendance.fields.source')" hide-label class="md:w-40">
            <option value="">{{ __('attendance.fields.source') }}: {{ __('app.common.all') }}</option>
            @foreach ($sources as $source)
                <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8" data-region="summary">
        @foreach ($statuses as $status)
            <a href="{{ route('admin.attendance.daily', [...request()->except(['status', 'page']), 'status' => $status->value]) }}"
                @class(['rounded-lg border bg-surface-default p-3 shadow-xs transition-colors hover:border-border-strong', 'border-action-primary-default' => ($filters['status'] ?? null) === $status->value, 'border-border-subtle' => ($filters['status'] ?? null) !== $status->value])>
                <x-status-badge :state="$status->badge()">{{ $status->label() }}</x-status-badge>
                <span class="mt-2 block text-numeric-md text-text-primary tabular-nums" data-count="{{ $status->value }}">{{ $counts[$status->value] ?? 0 }}</span>
            </a>
        @endforeach
        <div class="rounded-lg border border-border-subtle bg-surface-default p-3 shadow-xs">
            <x-badge tone="info">{{ __('attendance.common.manual') }}</x-badge>
            <span class="mt-2 block text-numeric-md text-text-primary tabular-nums">{{ $manualCount }}</span>
        </div>
    </div>

    <x-table data-region="table" :caption="__('attendance.daily.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('attendance.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.shift') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.check_in') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.check_out') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.worked') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.late') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.early_leave') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.overtime') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.status') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.source') }}</x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($days as $day)
            <x-table.row data-href="{{ route('admin.attendance.days.show', $day) }}">
                <x-table.td :label="__('attendance.fields.employee')">
                    <a href="{{ route('admin.attendance.days.show', $day) }}" class="block text-label-md text-text-link hover:underline">{{ $day->employee->name }}</a>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $day->employee->employee_number }} · {{ $day->department?->code }}</span>
                </x-table.td>
                <x-table.td :label="__('attendance.fields.shift')">
                    @if ($day->shift)
                        <span class="text-label-md">{{ $day->shift->code }}</span>
                        <span class="block text-caption-md text-text-tertiary tabular-nums" dir="ltr">{{ $day->shift->timeRange() }}</span>
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('attendance.fields.check_in')" class="tabular-nums"><span dir="ltr">{{ AttendanceClock::localTime($day->first_in_at, $day->timezone) ?? '—' }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.check_out')" class="tabular-nums"><span dir="ltr">{{ AttendanceClock::localTime($day->last_out_at, $day->timezone) ?? '—' }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.worked')" class="tabular-nums"><span dir="ltr">{{ Duration::format($day->worked_minutes) }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.late')" class="tabular-nums"><span dir="ltr">{{ Duration::format($day->late_minutes) }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.early_leave')" class="tabular-nums"><span dir="ltr">{{ Duration::format($day->early_leave_minutes) }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.overtime')" class="tabular-nums"><span dir="ltr">{{ Duration::format($day->overtime_minutes) }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.status')">
                    @include('admin.attendance._day-status')
                    @foreach (array_diff($day->flags ?? [], ['manual']) as $flag)
                        <span class="mt-1 block text-caption-sm text-text-tertiary">{{ __('attendance.flags.'.$flag) }}</span>
                    @endforeach
                </x-table.td>
                <x-table.td :label="__('attendance.fields.source')">@include('admin.attendance._sources', ['sources' => $day->sources ?? []])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="10" icon="clock" :title="__('attendance.daily.empty')" :description="__('attendance.daily.empty_help')" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$days" />
        </x-slot:footer>
    </x-table>
@endsection
