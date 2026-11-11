@extends('layouts.admin', ['subtitle' => __('payroll.statutory.subtitle')])

@section('title', __('payroll.statutory.title'))

@section('page-actions')
    @can('create', App\Models\StatutoryRuleSet::class)
        <x-button :href="route('admin.statutory-rules.create')" icon="plus">{{ __('payroll.statutory.create') }}</x-button>
    @endcan
@endsection

@section('content')
    @if ($country === '')
        <x-alert tone="warning">{{ __('payroll.statutory.country_unset') }}</x-alert>
    @else
        <x-alert tone="info">{{ __('payroll.statutory.country_setting', ['country' => $country]) }}</x-alert>
    @endif

    <x-filter-bar :action="route('admin.statutory-rules.index')" :reset="route('admin.statutory-rules.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('payroll.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('payroll.fields.status') }}: {{ __('app.common.all') }}</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('structure.common.active') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('structure.common.inactive') }}</option>
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('payroll.statutory.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('payroll.fields.name') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.country_code') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.type') }}</x-table.th>
                <x-table.th>{{ __('payroll.common.current') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            @php($current = $record->versionOn($today))
            <x-table.row :data-href="route('admin.statutory-rules.show', $record)">
                <x-table.td :label="__('payroll.fields.name')">
                    <a href="{{ route('admin.statutory-rules.show', $record) }}" class="block text-label-md text-text-primary hover:underline">{{ $record->name }}</a>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $record->code }}</span>
                    @if ($record->is_demo) @include('admin.payroll._demo') @endif
                </x-table.td>
                <x-table.td :label="__('payroll.fields.country_code')">
                    <span dir="ltr">{{ $record->country_code }}</span>
                    @if ($record->country_code !== $country) <span class="block text-caption-md text-text-tertiary">{{ __('payroll.statutory.not_payroll_country') }}</span> @endif
                </x-table.td>
                <x-table.td :label="__('payroll.fields.type')">{{ $record->type->label() }}</x-table.td>
                <x-table.td :label="__('payroll.common.current')" class="text-body-sm">
                    @if ($current)
                        {{ __('payroll.common.version', ['version' => $current->version]) }}
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $current->effective_from->toDateString() }}</span>
                    @else
                        <span class="text-text-tertiary">{{ __('payroll.statutory.no_version') }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('payroll.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'statutory-rules', 'show' => true])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="shield-check" :title="__('payroll.statutory.empty')" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
