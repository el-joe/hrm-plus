@extends('layouts.admin', ['subtitle' => __('schedules.statuses.subtitle')])

@section('title', __('schedules.statuses.title'))

@section('page-actions')
    @can('create', App\Models\AttendanceStatus::class)
        <x-button :href="route('admin.attendance-statuses.create')" icon="plus">{{ __('schedules.statuses.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-table :caption="__('schedules.statuses.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('schedules.fields.code') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('schedules.fields.name') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.category') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.counts_as_worked') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.is_paid') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('schedules.fields.code')">
                    <span dir="ltr" class="text-label-md">{{ $record->code }}</span>
                    @if ($record->is_system)
                        <x-badge class="ms-1">{{ __('schedules.statuses.system') }}</x-badge>
                    @endif
                </x-table.td>
                <x-table.td :label="__('schedules.fields.name')">{{ $record->name }}</x-table.td>
                <x-table.td :label="__('schedules.fields.category')">{{ $record->category->label() }}</x-table.td>
                <x-table.td :label="__('schedules.fields.counts_as_worked')">{{ $record->counts_as_worked ? __('schedules.common.yes') : __('schedules.common.no') }}</x-table.td>
                <x-table.td :label="__('schedules.fields.is_paid')">{{ $record->is_paid ? __('schedules.common.yes') : __('schedules.common.no') }}</x-table.td>
                <x-table.td :label="__('schedules.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        @can('update', $record)
                            <x-icon-button icon="pencil" size="sm" :href="route('admin.attendance-statuses.edit', $record)" :label="__('app.actions.edit')" />
                        @endcan
                        @can('delete', $record)
                            <form method="POST" action="{{ route('admin.attendance-statuses.destroy', $record) }}"
                                data-confirm="{{ __('structure.common.confirm_delete', ['name' => $record->name]) }}" data-confirm-label="{{ __('app.actions.delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endcan
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="layout-grid" />
        @endforelse
    </x-table>
@endsection
