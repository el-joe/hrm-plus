@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('structure.branches.title'), 'url' => route('admin.branches.index')]],
    'subtitle' => $branch->code,
])

@section('title', $branch->name)

@section('page-actions')
    @can('update', $branch)
        <x-button variant="secondary" :href="route('admin.branches.edit', $branch)" icon="pencil">{{ __('app.actions.edit') }}</x-button>
        <x-button :href="route('admin.branches.locations.create', $branch)" icon="plus">{{ __('structure.locations.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('structure.common.details')" class="lg:col-span-2">
            <x-slot:actions>
                @if ($branch->is_headquarters)
                    <x-badge tone="primary">{{ __('structure.branches.headquarters') }}</x-badge>
                @endif
                @include('admin.structure._status-badge', ['active' => $branch->is_active])
            </x-slot:actions>
            <x-detail-list>
                <x-detail-item :label="__('structure.fields.name_ar')"><span lang="ar" dir="rtl">{{ $branch->name_ar }}</span></x-detail-item>
                <x-detail-item :label="__('structure.fields.name_en')"><span lang="en" dir="ltr">{{ $branch->name_en }}</span></x-detail-item>
                <x-detail-item :label="__('structure.fields.email')"><span dir="ltr">{{ $branch->email ?? '—' }}</span></x-detail-item>
                <x-detail-item :label="__('structure.fields.phone')"><span dir="ltr">{{ $branch->phone ?? '—' }}</span></x-detail-item>
                <x-detail-item :label="__('structure.fields.address')">{{ $branch->localized('address') ?? '—' }}</x-detail-item>
                <x-detail-item :label="__('structure.fields.country_code')">{{ $branch->country_code ?? '—' }}</x-detail-item>
                <x-detail-item :label="__('structure.branches.manager')">
                    {{ $branch->managerPosition ? $branch->managerPosition->name.' · '.$branch->managerPosition->department?->name : __('structure.common.not_set') }}
                </x-detail-item>
                <x-detail-item :label="__('structure.fields.timezone')"><span dir="ltr">{{ $timezone }}</span></x-detail-item>
            </x-detail-list>
        </x-card>

        <div class="flex flex-col gap-4">
            <x-stat-card :label="__('structure.branches.locations')" :value="$locations->where('is_active', true)->count()" icon="map-pin" />
            <x-stat-card :label="__('structure.branches.departments')" :value="$departments->count()" icon="layers" />
        </div>
    </div>

    <x-card :title="__('structure.locations.title')" :subtitle="__('structure.locations.subtitle')" padding="none">
        <x-table :caption="__('structure.locations.title')">
            <x-slot:head>
                <tr>
                    <x-table.th class="min-w-48">{{ __('structure.fields.name') }}</x-table.th>
                    <x-table.th>{{ __('structure.locations.coordinates') }}</x-table.th>
                    <x-table.th>{{ __('structure.locations.radius') }}</x-table.th>
                    <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                    <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($locations as $location)
                <x-table.row>
                    <x-table.td :label="__('structure.fields.name')">
                        <span class="block text-label-md text-text-primary">{{ $location->name }}</span>
                        @if ($location->address)
                            <span class="block text-caption-md text-text-tertiary">{{ $location->address }}</span>
                        @endif
                    </x-table.td>
                    <x-table.td :label="__('structure.locations.coordinates')" class="whitespace-nowrap tabular-nums text-text-secondary"><span dir="ltr">{{ $location->latitude }}, {{ $location->longitude }}</span></x-table.td>
                    <x-table.td :label="__('structure.locations.radius')" class="tabular-nums">{{ __('structure.locations.meters', ['value' => $location->radius_meters]) }}</x-table.td>
                    <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $location->is_active])</x-table.td>
                    <x-table.td>
                        @can('update', $branch)
                            <div class="flex items-center justify-end gap-1">
                                <x-icon-button icon="pencil" size="sm" :href="route('admin.branches.locations.edit', [$branch, $location])" :label="__('app.actions.edit')" />
                                <form method="POST" action="{{ route('admin.branches.locations.destroy', [$branch, $location]) }}"
                                    data-confirm="{{ __('structure.common.confirm_delete', ['name' => $location->name]) }}"
                                    data-confirm-title="{{ __('app.actions.delete') }}: {{ $location->name }}" data-confirm-label="{{ __('app.actions.delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                                </form>
                            </div>
                        @endcan
                    </x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="5" icon="map-pin" :title="__('structure.branches.no_locations')" />
            @endforelse
        </x-table>
    </x-card>

    <x-card :title="__('structure.branches.departments')" padding="none">
        <x-table :caption="__('structure.branches.departments')">
            <x-slot:head>
                <tr>
                    <x-table.th class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                    <x-table.th>{{ __('structure.departments.positions') }}</x-table.th>
                    <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($departments as $department)
                <x-table.row>
                    <x-table.td :label="__('structure.fields.name')">
                        @include('admin.structure._name-cell', [
                            'record' => $department,
                            'url' => auth()->user()->can('view', $department) ? route('admin.departments.show', $department) : null,
                            'sub' => $department->parent?->name ?? __('structure.departments.top_level'),
                        ])
                    </x-table.td>
                    <x-table.td :label="__('structure.departments.positions')" class="tabular-nums">{{ $department->positions_count }}</x-table.td>
                    <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $department->is_active])</x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="3" icon="layers" :title="__('structure.branches.no_departments')" />
            @endforelse
        </x-table>
    </x-card>
@endsection
