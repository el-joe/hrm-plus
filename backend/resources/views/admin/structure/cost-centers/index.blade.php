@extends('layouts.admin', ['subtitle' => __('structure.cost_centers.subtitle')])

@section('title', __('structure.cost_centers.title'))

@section('page-actions')
    @can('create', App\Models\CostCenter::class)
        <x-button :href="route('admin.cost-centers.create')" icon="plus">{{ __('structure.cost_centers.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.cost-centers.index')" :reset="route('admin.cost-centers.index')" data-region="filters">
        @include('admin.structure._filters')
        @include('admin.structure._id-filter', ['name' => 'parent_id', 'label' => __('structure.cost_centers.parent'), 'options' => $parents])
    </x-filter-bar>

    <x-table data-region="table" :caption="__('structure.cost_centers.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="code">{{ __('structure.fields.code') }}</x-table.th>
                <x-table.th sort="name" class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                <x-table.th>{{ __('structure.cost_centers.parent') }}</x-table.th>
                <x-table.th sort="departments">{{ __('structure.cost_centers.departments') }}</x-table.th>
                <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('structure.fields.code')"><span dir="ltr" class="text-label-md">{{ $record->code }}</span></x-table.td>
                <x-table.td :label="__('structure.fields.name')" class="text-label-md text-text-primary">{{ $record->name }}</x-table.td>
                <x-table.td :label="__('structure.cost_centers.parent')" class="text-text-secondary">{{ $record->parent?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('structure.cost_centers.departments')" class="tabular-nums">{{ $record->departments_count }}</x-table.td>
                <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'cost-centers'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="coins" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
