@extends('layouts.admin', ['subtitle' => __('schedules.work_schedules.subtitle')])

@section('title', __('schedules.work_schedules.title'))

@section('page-actions')
    @can('create', App\Models\WorkSchedule::class)
        <x-button :href="route('admin.work-schedules.create')" icon="plus">{{ __('schedules.work_schedules.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.work-schedules.index')" :reset="route('admin.work-schedules.index')">
        @include('admin.structure._filters')
    </x-filter-bar>

    <x-table :caption="__('schedules.work_schedules.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="code">{{ __('schedules.fields.code') }}</x-table.th>
                <x-table.th sort="name" class="min-w-48">{{ __('schedules.fields.name') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.type') }}</x-table.th>
                <x-table.th>{{ __('schedules.work_schedules.pattern') }}</x-table.th>
                <x-table.th>{{ __('schedules.assignments.title') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            @php
                $length = $record->patternLength();
                $working = $record->days->pluck('day_index')->unique()->count();
                $codes = $record->days->pluck('shift.code')->unique()->filter()->implode(', ');
            @endphp
            <x-table.row>
                <x-table.td :label="__('schedules.fields.code')"><span dir="ltr" class="text-label-md">{{ $record->code }}</span></x-table.td>
                <x-table.td :label="__('schedules.fields.name')"><span class="text-label-md text-text-primary">{{ $record->name }}</span></x-table.td>
                <x-table.td :label="__('schedules.fields.type')">
                    {{ $record->type->label() }}
                    @if ($record->type === App\Enums\ScheduleType::Rotating)
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $record->cycle_length_days }}d · {{ $record->cycle_start_date?->format('Y-m-d') }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('schedules.work_schedules.pattern')" class="text-body-sm">
                    {{ __('schedules.work_schedules.pattern_summary', ['work' => $working, 'rest' => max(0, $length - $working)]) }}
                    @if ($codes)
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $codes }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('schedules.assignments.title')" class="tabular-nums">
                    <a href="{{ route('admin.schedule-assignments.index', ['work_schedule_id' => $record->id]) }}" class="text-text-link hover:underline">{{ $record->assignments_count }}</a>
                </x-table.td>
                <x-table.td :label="__('schedules.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'work-schedules'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="layers" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
