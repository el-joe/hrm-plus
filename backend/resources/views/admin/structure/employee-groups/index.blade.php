@extends('layouts.admin', ['subtitle' => __('employees.groups.subtitle')])

@section('title', __('employees.groups.title'))

@section('page-actions')
    @can('create', App\Models\EmployeeGroup::class)
        <x-button :href="route('admin.employee-groups.create')" icon="plus">{{ __('employees.groups.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.employee-groups.index')" :reset="route('admin.employee-groups.index')" data-region="filters">
        @include('admin.structure._filters')
    </x-filter-bar>

    <x-table data-region="table" :caption="__('employees.groups.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="code">{{ __('structure.fields.code') }}</x-table.th>
                <x-table.th sort="name" class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                <x-table.th sort="employees">{{ __('employees.groups.members') }}</x-table.th>
                <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('structure.fields.code')"><span dir="ltr" class="text-label-md">{{ $record->code }}</span></x-table.td>
                <x-table.td :label="__('structure.fields.name')">
                    <span class="block text-label-md text-text-primary">{{ $record->name }}</span>
                    @if ($record->description)
                        <span class="block max-w-md truncate text-caption-md text-text-tertiary">{{ $record->description }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('employees.groups.members')" class="tabular-nums">
                    @can('viewAny', App\Models\Employee::class)
                        <a href="{{ route('admin.employees.index', ['group_id' => $record->id]) }}" class="text-text-link hover:underline">{{ $record->employees_count }}</a>
                    @else
                        {{ $record->employees_count }}
                    @endcan
                </x-table.td>
                <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'employee-groups'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="users" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
