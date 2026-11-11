@extends('layouts.admin', ['subtitle' => __('structure.branches.subtitle')])

@section('title', __('structure.branches.title'))

@section('page-actions')
    @can('create', App\Models\Branch::class)
        <x-button :href="route('admin.branches.create')" icon="plus">{{ __('structure.branches.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.branches.index')" :reset="route('admin.branches.index')" data-region="filters">
        @include('admin.structure._filters')
    </x-filter-bar>

    <x-table data-region="table" :caption="__('structure.branches.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="name" class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                <x-table.th>{{ __('structure.branches.manager') }}</x-table.th>
                <x-table.th sort="locations">{{ __('structure.branches.locations') }}</x-table.th>
                <x-table.th sort="departments">{{ __('structure.branches.departments') }}</x-table.th>
                <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('structure.fields.name')">
                    <div class="flex items-center gap-2">
                        @include('admin.structure._name-cell', ['url' => route('admin.branches.show', $record), 'sub' => $record->localized('address')])
                        @if ($record->is_headquarters)
                            <x-badge tone="primary">{{ __('structure.branches.headquarters') }}</x-badge>
                        @endif
                    </div>
                </x-table.td>
                <x-table.td :label="__('structure.branches.manager')" class="text-text-secondary">{{ $record->managerPosition?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('structure.branches.locations')" class="tabular-nums">{{ $record->locations_count }}</x-table.td>
                <x-table.td :label="__('structure.branches.departments')" class="tabular-nums">{{ $record->departments_count }}</x-table.td>
                <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'branches', 'show' => true])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="map-pin" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
