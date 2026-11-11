@extends('layouts.admin', ['subtitle' => __('structure.employment_types.subtitle')])

@section('title', __('structure.employment_types.title'))

@section('page-actions')
    @can('create', App\Models\EmploymentType::class)
        <x-button :href="route('admin.employment-types.create')" icon="plus">{{ __('structure.employment_types.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.employment-types.index')" :reset="route('admin.employment-types.index')" data-region="filters">
        @include('admin.structure._filters')
    </x-filter-bar>

    <x-table data-region="table" :caption="__('structure.employment_types.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="code">{{ __('structure.fields.code') }}</x-table.th>
                <x-table.th sort="name" class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                <x-table.th sort="fixed_term">{{ __('structure.fields.is_fixed_term') }}</x-table.th>
                <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('structure.fields.code')"><span dir="ltr" class="text-label-md">{{ $record->code }}</span></x-table.td>
                <x-table.td :label="__('structure.fields.name')" class="text-label-md text-text-primary">{{ $record->name }}</x-table.td>
                <x-table.td :label="__('structure.fields.is_fixed_term')">
                    <x-badge :tone="$record->is_fixed_term ? 'info' : 'neutral'">{{ $record->is_fixed_term ? __('structure.employment_types.fixed_term') : __('structure.employment_types.open_ended') }}</x-badge>
                </x-table.td>
                <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'employment-types'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="file-text" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
