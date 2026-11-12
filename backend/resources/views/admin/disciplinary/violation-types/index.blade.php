@extends('layouts.admin', ['subtitle' => __('disciplinary.violation_types.subtitle')])

@section('title', __('disciplinary.violation_types.title'))

@section('page-actions')
    @can('create', App\Models\ViolationType::class)
        <x-button :href="route('admin.violation-types.create')" icon="plus">{{ __('disciplinary.violation_types.create') }}</x-button>
    @endcan
@endsection

@section('content')
    @error('record')
        <x-alert tone="danger">{{ $message }}</x-alert>
    @enderror

    <x-filter-bar :action="route('admin.violation-types.index')" :reset="route('admin.violation-types.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('disciplinary.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('disciplinary.fields.status') }}: {{ __('app.common.all') }}</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('structure.common.active') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('structure.common.inactive') }}</option>
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('disciplinary.violation_types.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('structure.fields.name_en') }}</x-table.th>
                <x-table.th>{{ __('disciplinary.fields.severity') }}</x-table.th>
                <x-table.th>{{ __('disciplinary.fields.default_action') }}</x-table.th>
                <x-table.th>{{ __('disciplinary.violation_types.cases_count') }}</x-table.th>
                <x-table.th>{{ __('disciplinary.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('structure.fields.name_en')">
                    <span class="block text-label-md text-text-primary">{{ $record->name }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $record->code }}</span>
                </x-table.td>
                <x-table.td :label="__('disciplinary.fields.severity')"><x-badge :tone="$record->severity->tone()">{{ $record->severity->label() }}</x-badge></x-table.td>
                <x-table.td :label="__('disciplinary.fields.default_action')">{{ $record->default_action->label() }}</x-table.td>
                <x-table.td :label="__('disciplinary.violation_types.cases_count')" class="tabular-nums">{{ $record->cases_count }}</x-table.td>
                <x-table.td :label="__('disciplinary.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'violation-types'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="layers" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
