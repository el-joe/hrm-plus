@php
    use App\Services\Attendance\AttendanceClock;
    $badges = ['present' => 'present', 'late' => 'late', 'absent' => 'absent', 'on_leave' => 'on-leave', 'not_in' => 'draft'];
    $tz = (string) setting('localisation.timezone');
@endphp
@extends('layouts.admin', ['subtitle' => __('attendance_reports.dashboard.subtitle')])

@section('title', __('attendance_reports.dashboard.title'))

@section('page-actions')
    <x-button variant="secondary" icon="clock" :href="route('admin.attendance.daily', ['date' => $filters['date']])">{{ __('attendance.daily.title') }}</x-button>
    @can('viewAttendanceReports')
        <x-button variant="secondary" icon="file-text" :href="route('admin.attendance.reports.index')">{{ __('attendance_reports.index.title') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.attendance.dashboard')" :reset="route('admin.attendance.dashboard')">
        <x-form.input name="date" type="date" :label="__('attendance.fields.date')" hide-label :value="$filters['date']" class="md:w-44" />
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
    </x-filter-bar>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6" data-region="totals">
        <x-stat-card :label="__('attendance_reports.dashboard.expected')" :value="(string) $data['expected']" icon="users"
            :hint="$data['attendance_rate'] !== null ? __('attendance_reports.dashboard.rate', ['rate' => $data['attendance_rate']]) : null" />
        @foreach (['present', 'late', 'absent', 'on_leave', 'not_in'] as $category)
            @continue($category === 'not_in' && ! $data['is_today'])
            <a href="{{ in_array($category, ['present', 'late', 'absent', 'on_leave'], true) ? route('admin.attendance.daily', ['date' => $filters['date'], 'status' => $category, 'branch_id' => $filters['branch_id'] ?? null, 'department_id' => $filters['department_id'] ?? null]) : '#not-in' }}"
                class="flex flex-col gap-3 rounded-xl border border-border-subtle bg-surface-default p-5 shadow-xs transition-shadow hover:shadow-sm">
                <x-status-badge :state="$badges[$category]">{{ __('attendance_reports.categories.'.$category) }}</x-status-badge>
                <span class="text-numeric-lg text-text-primary tabular-nums" data-count="{{ $category }}">{{ $data['totals'][$category] }}</span>
            </a>
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-2 xl:gap-6">
        @foreach (['by_branch' => 'branch', 'by_department' => 'department'] as $key => $label)
            <x-card :title="__('attendance_reports.dashboard.'.$key)" padding="none" data-region="{{ $key }}">
                <x-table :caption="__('attendance_reports.dashboard.'.$key)">
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('attendance.fields.'.$label) }}</x-table.th>
                            <x-table.th class="min-w-40">{{ __('attendance_reports.dashboard.breakdown') }}</x-table.th>
                            <x-table.th>{{ __('attendance_reports.dashboard.expected') }}</x-table.th>
                            <x-table.th>{{ __('attendance_reports.categories.present') }}</x-table.th>
                            <x-table.th>{{ __('attendance_reports.categories.late') }}</x-table.th>
                            <x-table.th>{{ __('attendance_reports.categories.absent') }}</x-table.th>
                            <x-table.th>{{ __('attendance_reports.categories.on_leave') }}</x-table.th>
                            @if ($data['is_today'])<x-table.th>{{ __('attendance_reports.categories.not_in') }}</x-table.th>@endif
                        </tr>
                    </x-slot:head>
                    @forelse ($data[$key] as $row)
                        <x-table.row>
                            <x-table.td :label="__('attendance.fields.'.$label)"><span class="text-label-md">{{ $row['code'] }}</span> <span class="block text-caption-md text-text-tertiary">{{ $row['name'] }}</span></x-table.td>
                            <x-table.td :label="__('attendance_reports.dashboard.breakdown')">@include('admin.attendance._category-bar')</x-table.td>
                            <x-table.td :label="__('attendance_reports.dashboard.expected')" class="tabular-nums">{{ $row['expected'] }}</x-table.td>
                            <x-table.td :label="__('attendance_reports.categories.present')" class="tabular-nums">{{ $row['present'] }}</x-table.td>
                            <x-table.td :label="__('attendance_reports.categories.late')" class="tabular-nums">{{ $row['late'] }}</x-table.td>
                            <x-table.td :label="__('attendance_reports.categories.absent')" class="tabular-nums">{{ $row['absent'] }}</x-table.td>
                            <x-table.td :label="__('attendance_reports.categories.on_leave')" class="tabular-nums">{{ $row['on_leave'] }}</x-table.td>
                            @if ($data['is_today'])<x-table.td :label="__('attendance_reports.categories.not_in')" class="tabular-nums">{{ $row['not_in'] }}</x-table.td>@endif
                        </x-table.row>
                    @empty
                        <x-table.empty :colspan="8" icon="users" :title="__('attendance_reports.dashboard.nobody_scheduled')" />
                    @endforelse
                </x-table>
            </x-card>
        @endforeach
    </div>

    <div class="grid gap-4 xl:grid-cols-3 xl:gap-6">
        <x-card :title="__('attendance_reports.dashboard.latest_punches')" :subtitle="__('attendance_reports.dashboard.latest_help')" padding="none" class="xl:col-span-2" data-region="latest-punches">
            <ul class="divide-y divide-border-subtle">
                @forelse ($data['latest_punches'] as $punch)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span class="min-w-0">
                            <span class="block truncate text-label-md">{{ $punch->employee?->name }}</span>
                            <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $punch->employee?->employee_number }} · {{ $punch->employee?->branch?->code }}</span>
                        </span>
                        <span class="flex items-center gap-2">
                            @include('admin.attendance._sources', ['sources' => [$punch->source]])
                            <x-badge :tone="$punch->direction->value === 'in' ? 'success' : 'neutral'">{{ $punch->direction->label() }}</x-badge>
                            <span class="text-label-md tabular-nums" dir="ltr">{{ $punch->punched_at->setTimezone($punch->rawEvent?->timezone ?? $tz)->format('H:i') }}</span>
                        </span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-body-sm text-text-tertiary">{{ __('attendance_reports.dashboard.no_punches') }}</li>
                @endforelse
            </ul>
        </x-card>

        <div class="flex flex-col gap-4">
            <x-card :title="__('attendance_reports.dashboard.pending')" data-region="pending">
                <ul class="flex flex-col divide-y divide-border-subtle">
                    @foreach ([
                        'reviews' => route('admin.attendance.reviews.index'),
                        'corrections' => route('admin.attendance.corrections.index'),
                        'batches' => route('admin.attendance.batches.index'),
                        'overtime' => route('admin.attendance.reports.show', ['overtime']),
                        'unmatched' => route('admin.attendance.events.index', ['state' => 'unmatched']),
                    ] as $key => $url)
                        <li class="flex items-center justify-between gap-3 py-2">
                            <a href="{{ $url }}" class="text-body-md text-text-link hover:underline">{{ __('attendance_reports.dashboard.pending_items.'.$key) }}</a>
                            <x-badge :tone="$data['pending'][$key] > 0 ? 'warning' : 'neutral'">{{ $data['pending'][$key] }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            @if ($data['devices'] !== null)
                <x-card :title="__('attendance_reports.dashboard.devices')" data-region="device-health">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($data['devices']['by_health'] as $health => $count)
                            @if ($count > 0)
                                <x-badge :tone="\App\Enums\DeviceHealth::from($health)->tone()" dot>{{ \App\Enums\DeviceHealth::from($health)->label() }}: {{ $count }}</x-badge>
                            @endif
                        @endforeach
                        @if ($data['devices']['simulated'] > 0)
                            @include('admin.attendance._simulated')
                        @endif
                    </div>
                    @if ($data['devices']['total'] === 0)
                        <p class="text-body-sm text-text-tertiary">{{ __('devices.index.empty') }}</p>
                    @endif
                    <ul class="mt-3 flex flex-col gap-2">
                        @foreach ($data['devices']['problems'] as $device)
                            <li class="flex items-center justify-between gap-2">
                                <a href="{{ route('admin.attendance-devices.show', $device) }}" class="truncate text-body-sm text-text-link hover:underline">{{ $device->name }}</a>
                                <x-badge :tone="$device->health()->tone()">{{ $device->health()->label() }}</x-badge>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>

    @if ($data['is_today'] && $data['not_in'] !== [])
        <x-card id="not-in" :title="__('attendance_reports.dashboard.not_in_list')" padding="none" data-region="not-in">
            <ul class="divide-y divide-border-subtle">
                @foreach ($data['not_in'] as $row)
                    <li class="flex items-center justify-between gap-3 px-5 py-2.5">
                        <span><span class="text-label-md">{{ $row['employee']->name }}</span> <span class="text-caption-md text-text-tertiary" dir="ltr">{{ $row['employee']->employee_number }}</span></span>
                        @if ($row['start'])<span class="text-caption-md text-text-secondary tabular-nums" dir="ltr">{{ __('attendance_reports.dashboard.starts', ['time' => $row['start']->setTimezone($row['timezone'] ?? $tz)->format('H:i')]) }}</span>@endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
