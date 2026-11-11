@extends('layouts.admin', ['subtitle' => __('schedules.shifts.subtitle')])

@section('title', __('schedules.shifts.title'))

@section('page-actions')
    @can('create', App\Models\Shift::class)
        <x-button :href="route('admin.shifts.create')" icon="plus">{{ __('schedules.shifts.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.shifts.index')" :reset="route('admin.shifts.index')">
        @include('admin.structure._filters')
        <x-form.select name="type" :label="__('schedules.fields.type')" hide-label class="md:w-40">
            <option value="">{{ __('schedules.fields.type') }}: {{ __('app.common.all') }}</option>
            @foreach (App\Enums\ShiftType::cases() as $type)
                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('schedules.shifts.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="code">{{ __('schedules.fields.code') }}</x-table.th>
                <x-table.th sort="name" class="min-w-48">{{ __('schedules.fields.name') }}</x-table.th>
                <x-table.th sort="start">{{ __('schedules.fields.times') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.type') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.breaks') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.expected_minutes') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('schedules.fields.code')">
                    <span class="inline-flex items-center gap-2">
                        @include('admin.scheduling._color-swatch', ['color' => $record->color])
                        <span dir="ltr" class="text-label-md">{{ $record->code }}</span>
                    </span>
                </x-table.td>
                <x-table.td :label="__('schedules.fields.name')"><span class="text-label-md text-text-primary">{{ $record->name }}</span></x-table.td>
                <x-table.td :label="__('schedules.fields.times')">
                    <span dir="ltr" class="tabular-nums">{{ $record->timeRange() }}</span>
                    <span class="mt-1 flex flex-wrap gap-1">
                        @if ($record->crossesMidnight())
                            <x-badge tone="info">{{ __('schedules.common.crosses_midnight') }}</x-badge>
                        @endif
                        @if ($record->is_night)
                            <x-badge tone="primary">{{ __('schedules.common.night') }}</x-badge>
                        @endif
                    </span>
                </x-table.td>
                <x-table.td :label="__('schedules.fields.type')">{{ $record->type->label() }}</x-table.td>
                <x-table.td :label="__('schedules.fields.breaks')" class="text-body-sm">
                    @forelse ($record->breaks as $break)
                        <span class="block">
                            {{ $break->name ?: $break->type->label() }}:
                            {{ trans('schedules.common.minutes', ['count' => $break->duration_minutes]) }}{{ $break->is_paid ? ' · '.__('schedules.fields.is_paid') : '' }}
                        </span>
                    @empty
                        <span class="text-text-tertiary">{{ __('schedules.shifts.no_breaks') }}</span>
                    @endforelse
                </x-table.td>
                <x-table.td :label="__('schedules.fields.expected_minutes')" class="tabular-nums">{{ trans('schedules.common.minutes', ['count' => $record->expectedMinutes()]) }}</x-table.td>
                <x-table.td :label="__('schedules.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'shifts'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="8" icon="clock" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
