@extends('layouts.admin', ['subtitle' => __('payroll.structures.subtitle')])

@section('title', __('payroll.structures.title'))

@section('page-actions')
    @can('create', App\Models\SalaryStructure::class)
        <x-button :href="route('admin.salary-structures.create')" icon="plus">{{ __('payroll.structures.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <x-filter-bar :action="route('admin.salary-structures.index')" :reset="route('admin.salary-structures.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('payroll.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('payroll.fields.status') }}: {{ __('app.common.all') }}</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>{{ __('structure.common.active') }}</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>{{ __('structure.common.inactive') }}</option>
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('payroll.structures.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('payroll.fields.name') }}</x-table.th>
                <x-table.th>{{ __('payroll.structures.current_version') }}</x-table.th>
                <x-table.th>{{ __('payroll.structures.versions_count') }}</x-table.th>
                <x-table.th>{{ __('payroll.structures.current_assignments') }}</x-table.th>
                <x-table.th>{{ __('payroll.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>

        @forelse ($records as $record)
            @php($current = $record->versionOn($today))
            <x-table.row :data-href="route('admin.salary-structures.show', $record)">
                <x-table.td :label="__('payroll.fields.name')">
                    <a href="{{ route('admin.salary-structures.show', $record) }}" class="block text-label-md text-text-primary hover:underline">{{ $record->name }}</a>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $record->code }}</span>
                </x-table.td>
                <x-table.td :label="__('payroll.structures.current_version')">
                    @if ($current)
                        {{ __('payroll.common.version', ['version' => $current->version]) }}
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $current->effective_from->toDateString() }}</span>
                    @else
                        <span class="text-text-tertiary">{{ __('payroll.structures.no_version') }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('payroll.structures.versions_count')" class="tabular-nums">{{ $record->versions_count }}</x-table.td>
                <x-table.td :label="__('payroll.structures.current_assignments')" class="tabular-nums">{{ $record->current_assignments_count }}</x-table.td>
                <x-table.td :label="__('payroll.fields.status')">@include('admin.structure._status-badge', ['active' => $record->is_active])</x-table.td>
                <x-table.td>@include('admin.structure._row-actions', ['resource' => 'salary-structures', 'show' => true])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="layers" :title="__('payroll.structures.empty')" />
        @endforelse

        <x-slot:footer>
            <x-pagination :paginator="$records" />
        </x-slot:footer>
    </x-table>
@endsection
