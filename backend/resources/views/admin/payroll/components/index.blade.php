@extends('layouts.admin', ['subtitle' => __('payroll.components.subtitle')])

@section('title', __('payroll.components.title'))

@section('page-actions')
    @can('create', App\Models\SalaryComponent::class)
        <x-button :href="route('admin.salary-components.create')" icon="plus">{{ __('payroll.components.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.salary-components.index')" :reset="route('admin.salary-components.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="kind" :label="__('payroll.fields.kind')" hide-label class="md:w-48">
            <option value="">{{ __('payroll.fields.kind') }}: {{ __('app.common.all') }}</option>
            @foreach ($kinds as $kind)
                <option value="{{ $kind->value }}" @selected(($filters['kind'] ?? '') === $kind->value)>{{ $kind->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('payroll.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('payroll.fields.status') }}: {{ __('app.common.all') }}</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('structure.common.active') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('structure.common.inactive') }}</option>
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('payroll.components.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('payroll.fields.name') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.kind') }}</x-table.th>
                <x-table.th>{{ __('payroll.components.rule') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.treatment') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.display_order') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            <x-table.row>
                <x-table.td :label="__('payroll.fields.name')">
                    <span class="block text-label-md text-text-primary">{{ $record->name }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $record->code }}</span>
                </x-table.td>
                <x-table.td :label="__('payroll.fields.kind')">
                    <x-badge :tone="match ($record->kind->value) { 'earning' => 'success', 'deduction' => 'danger', default => 'info' }">{{ $record->kind->label() }}</x-badge>
                    <span class="block text-caption-md text-text-tertiary">{{ $record->category->label() }}</span>
                </x-table.td>
                <x-table.td :label="__('payroll.components.rule')" class="text-body-sm text-text-secondary">
                    @include('admin.payroll.components._rule', ['component' => $record])
                </x-table.td>
                <x-table.td :label="__('payroll.fields.treatment')" class="text-caption-md text-text-secondary">
                    @if ($record->is_taxable) <x-badge>{{ __('payroll.fields.is_taxable') }}</x-badge> @endif
                    @if ($record->is_insurable) <x-badge>{{ __('payroll.fields.is_insurable') }}</x-badge> @endif
                    @if ($record->requires_approval) <x-badge tone="warning">{{ __('payroll.fields.requires_approval') }}</x-badge> @endif
                </x-table.td>
                <x-table.td :label="__('payroll.fields.display_order')" class="tabular-nums">{{ $record->display_order }}</x-table.td>
                <x-table.td :label="__('payroll.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'salary-components'])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="coins" :title="__('payroll.components.empty')" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
