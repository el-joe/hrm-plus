@extends('layouts.admin', ['subtitle' => __('structure.teams.subtitle')])

@section('title', __('structure.teams.title'))

@section('page-actions')
    @can('create', App\Models\Team::class)
        <x-button :href="route('admin.teams.create')" icon="plus">{{ __('structure.teams.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.teams.index')" :reset="route('admin.teams.index')" data-region="filters">
        @include('admin.structure._filters')
        @include('admin.structure._id-filter', ['name' => 'department_id', 'label' => __('structure.fields.department_id'), 'options' => $departments])
    </x-filter-bar>

    <x-table data-region="table" :caption="__('structure.teams.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="code">{{ __('structure.fields.code') }}</x-table.th>
                <x-table.th sort="name" class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                <x-table.th sort="department">{{ __('structure.fields.department_id') }}</x-table.th>
                <x-table.th>{{ __('structure.fields.leader_employee_id') }}</x-table.th>
                <x-table.th>{{ __('structure.org_chart.employees') }}</x-table.th>
                <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('structure.fields.code')"><span dir="ltr" class="text-label-md">{{ $record->code }}</span></x-table.td>
                <x-table.td :label="__('structure.fields.name')" class="text-label-md text-text-primary">{{ $record->name }}</x-table.td>
                <x-table.td :label="__('structure.fields.department_id')">
                    @can('view', $record->department)
                        <a href="{{ route('admin.departments.show', $record->department) }}" class="text-text-link hover:underline">{{ $record->department->name }}</a>
                    @else
                        {{ $record->department?->name }}
                    @endcan
                </x-table.td>
                <x-table.td :label="__('structure.fields.leader_employee_id')" class="text-text-secondary">
                    @if ($record->leader)
                        @can('view', $record->leader)
                            <a href="{{ route('admin.employees.show', $record->leader) }}" class="text-text-link hover:underline">{{ $record->leader->name }}</a>
                        @else
                            {{ $record->leader->name }}
                        @endcan
                    @else
                        —
                    @endif
                </x-table.td>
                <x-table.td :label="__('structure.org_chart.employees')" class="tabular-nums">{{ $record->employees_count }}</x-table.td>
                <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'teams'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="users" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
