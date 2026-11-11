@extends('layouts.admin', ['subtitle' => __('schedules.holidays.subtitle')])

@section('title', __('schedules.holidays.title'))

@section('page-actions')
    @can('create', App\Models\Holiday::class)
        <x-button :href="route('admin.holidays.create')" icon="plus">{{ __('schedules.holidays.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.holidays.index')" :reset="route('admin.holidays.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.input name="year" type="number" :label="__('schedules.fields.year')" hide-label :value="$year" min="2000" max="2100" class="md:w-28" />
        <x-form.select name="branch_id" :label="__('schedules.fields.branch_id')" hide-label class="md:w-56">
            <option value="">{{ __('schedules.fields.branch_id') }}: {{ __('app.common.all') }}</option>
            <option value="company" @selected(($filters['branch_id'] ?? '') === 'company')>{{ __('schedules.common.all_branches') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('schedules.holidays.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('schedules.fields.name') }}</x-table.th>
                <x-table.th>{{ __('schedules.holidays.dates') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.days') }}</x-table.th>
                <x-table.th>{{ __('schedules.holidays.scope') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.is_paid') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            @php $days = (int) $record->start_date->diffInDays($record->end_date) + 1; @endphp
            <x-table.row>
                <x-table.td :label="__('schedules.fields.name')">
                    <span class="block text-label-md text-text-primary">{{ $record->name }}</span>
                    @if ($record->description)
                        <span class="block max-w-md truncate text-caption-md text-text-tertiary">{{ $record->description }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('schedules.holidays.dates')" class="tabular-nums">
                    @php
                        $start = $record->is_recurring ? $record->start_date->copy()->setYear($year) : $record->start_date;
                        $end = $start->copy()->addDays($days - 1);
                    @endphp
                    <span dir="ltr">{{ $start->format('Y-m-d') }}@if ($days > 1) – {{ $end->format('Y-m-d') }}@endif</span>
                    @if ($record->is_recurring)
                        <x-badge tone="info" class="ms-1">{{ __('schedules.fields.is_recurring') }}</x-badge>
                    @endif
                </x-table.td>
                <x-table.td :label="__('schedules.fields.days')">{{ trans_choice('schedules.holidays.days_count', $days, ['count' => $days]) }}</x-table.td>
                <x-table.td :label="__('schedules.holidays.scope')">{{ $record->branch ? $record->branch->code.' · '.$record->branch->name : __('schedules.common.company') }}</x-table.td>
                <x-table.td :label="__('schedules.fields.is_paid')">{{ $record->is_paid ? __('schedules.common.yes') : __('schedules.common.no') }}</x-table.td>
                <x-table.td :label="__('schedules.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        @can('update', $record)
                            <x-icon-button icon="pencil" size="sm" :href="route('admin.holidays.edit', $record)" :label="__('app.actions.edit')" />
                        @endcan
                        @can('delete', $record)
                            <form method="POST" action="{{ route('admin.holidays.destroy', $record) }}" data-confirm="{{ __('schedules.holidays.confirm_delete', ['name' => $record->name]) }}"
                                data-confirm-label="{{ __('app.actions.delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endcan
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="calendar" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
