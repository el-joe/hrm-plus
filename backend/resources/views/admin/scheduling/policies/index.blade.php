@extends('layouts.admin', ['subtitle' => __('schedules.policies.subtitle')])

@section('title', __('schedules.policies.title'))

@section('page-actions')
    @can('create', App\Models\AttendancePolicy::class)
        <x-button :href="route('admin.attendance-policies.create')" icon="plus">{{ __('schedules.policies.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.attendance-policies.index')" :reset="route('admin.attendance-policies.index')">
        @include('admin.structure._filters')
    </x-filter-bar>

    <x-table :caption="__('schedules.policies.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="code">{{ __('schedules.fields.code') }}</x-table.th>
                <x-table.th sort="name" class="min-w-56">{{ __('schedules.fields.name') }}</x-table.th>
                <x-table.th>{{ __('schedules.policies.versions') }}</x-table.th>
                <x-table.th>{{ __('schedules.policies.assignments') }}</x-table.th>
                <x-table.th>{{ __('schedules.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row :data-href="route('admin.attendance-policies.show', $record)">
                <x-table.td :label="__('schedules.fields.code')"><span dir="ltr" class="text-label-md">{{ $record->code }}</span></x-table.td>
                <x-table.td :label="__('schedules.fields.name')">
                    <a href="{{ route('admin.attendance-policies.show', $record) }}" class="text-label-md text-text-link hover:underline">{{ $record->name }}</a>
                    @if ($record->description)
                        <span class="block max-w-md truncate text-caption-md text-text-tertiary">{{ $record->description }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('schedules.policies.versions')" class="tabular-nums">{{ $record->versions_count }}</x-table.td>
                <x-table.td :label="__('schedules.policies.assignments')" class="tabular-nums">{{ $record->assignments_count }}</x-table.td>
                <x-table.td :label="__('schedules.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'attendance-policies', 'show' => true])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="shield-check" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
