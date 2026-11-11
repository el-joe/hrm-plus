@extends('layouts.admin', ['subtitle' => __('structure.departments.subtitle')])

@section('title', __('structure.departments.title'))

@section('page-actions')
    <x-button variant="secondary" :href="route('admin.org-chart')" icon="sitemap">{{ __('structure.org_chart.title') }}</x-button>
    @can('create', App\Models\Department::class)
        <x-button :href="route('admin.departments.create')" icon="plus">{{ __('structure.departments.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.departments.index')" :reset="route('admin.departments.index')" data-region="filters">
        @include('admin.structure._filters')
        @include('admin.structure._id-filter', ['name' => 'branch_id', 'label' => __('structure.fields.branch_id'), 'options' => $branches])
        @include('admin.structure._id-filter', ['name' => 'parent_id', 'label' => __('structure.departments.filter_parent'), 'options' => $parents])
        @include('admin.structure._id-filter', ['name' => 'cost_center_id', 'label' => __('structure.fields.cost_center_id'), 'options' => $costCenters])
    </x-filter-bar>

    <x-table data-region="table" :caption="__('structure.departments.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="name" class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                <x-table.th sort="branch">{{ __('structure.fields.branch_id') }}</x-table.th>
                <x-table.th>{{ __('structure.departments.head') }}</x-table.th>
                <x-table.th sort="positions">{{ __('structure.departments.positions') }}</x-table.th>
                <x-table.th>{{ __('structure.departments.headcount') }}</x-table.th>
                <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('structure.fields.name')">
                    @include('admin.structure._name-cell', [
                        'url' => route('admin.departments.show', $record),
                        'sub' => $record->parent?->name ?? __('structure.departments.top_level'),
                    ])
                </x-table.td>
                <x-table.td :label="__('structure.fields.branch_id')">{{ $record->branch?->name ?? __('structure.departments.company_wide') }}</x-table.td>
                <x-table.td :label="__('structure.departments.head')" class="text-text-secondary">{{ $record->headPosition?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('structure.departments.positions')" class="tabular-nums">{{ $record->positions_count }}</x-table.td>
                <x-table.td :label="__('structure.departments.headcount')" class="tabular-nums">{{ (int) $record->positions_sum_headcount }}</x-table.td>
                <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'departments', 'show' => true])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="layers" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
