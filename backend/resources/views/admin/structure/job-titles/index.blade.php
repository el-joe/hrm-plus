@extends('layouts.admin', ['subtitle' => __('structure.job_titles.subtitle')])

@section('title', __('structure.job_titles.title'))

@section('page-actions')
    @can('create', App\Models\JobTitle::class)
        <x-button :href="route('admin.job-titles.create')" icon="plus">{{ __('structure.job_titles.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.job-titles.index')" :reset="route('admin.job-titles.index')" data-region="filters">
        @include('admin.structure._filters')
    </x-filter-bar>

    <x-table data-region="table" :caption="__('structure.job_titles.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="code">{{ __('structure.fields.code') }}</x-table.th>
                <x-table.th sort="name" class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                <x-table.th sort="positions">{{ __('structure.departments.positions') }}</x-table.th>
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
                <x-table.td :label="__('structure.departments.positions')" class="tabular-nums">{{ $record->positions_count }}</x-table.td>
                <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'job-titles'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="id-card" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
