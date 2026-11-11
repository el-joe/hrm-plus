@extends('layouts.admin', ['subtitle' => __('employees.custom_fields.subtitle')])

@section('title', __('employees.custom_fields.title'))

@section('page-actions')
    <x-button :href="route('admin.employee-custom-fields.create')" icon="plus">{{ __('employees.custom_fields.create') }}</x-button>
@endsection

@section('content')
    <x-table data-region="table" :caption="__('employees.custom_fields.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('employees.custom_fields.fields.code') }}</x-table.th>
                <x-table.th class="min-w-56">{{ __('structure.fields.name') }}</x-table.th>
                <x-table.th>{{ __('employees.custom_fields.fields.type') }}</x-table.th>
                <x-table.th>{{ __('employees.custom_fields.flags') }}</x-table.th>
                <x-table.th>{{ __('employees.custom_fields.values') }}</x-table.th>
                <x-table.th>{{ __('structure.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($fields as $field)
            <x-table.row>
                <x-table.td :label="__('employees.custom_fields.fields.code')"><code dir="ltr">{{ $field->code }}</code></x-table.td>
                <x-table.td :label="__('structure.fields.name')">
                    <span class="block text-label-md text-text-primary">{{ $field->name }}</span>
                    @if ($field->options->isNotEmpty())
                        <span class="block text-caption-md text-text-tertiary">{{ $field->options->map->label()->implode(' · ') }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('employees.custom_fields.fields.type')">{{ $field->type->label() }}</x-table.td>
                <x-table.td :label="__('employees.custom_fields.flags')">
                    <div class="flex flex-wrap gap-1">
                        @if ($field->is_required) <x-badge tone="warning">{{ __('employees.custom_fields.fields.is_required') }}</x-badge> @endif
                        @if ($field->visible_to_employee) <x-badge tone="info">{{ __('employees.custom_fields.fields.visible_to_employee') }}</x-badge> @endif
                    </div>
                </x-table.td>
                <x-table.td :label="__('employees.custom_fields.values')" class="tabular-nums">{{ $field->values_count }}</x-table.td>
                <x-table.td :label="__('structure.common.status')">@include('admin.structure._status-badge', ['active' => $field->is_active])</x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        <x-icon-button icon="pencil" size="sm" :href="route('admin.employee-custom-fields.edit', $field)" :label="__('app.actions.edit')" />
                        @if ($field->values_count === 0)
                            <form method="POST" action="{{ route('admin.employee-custom-fields.destroy', $field) }}"
                                data-confirm="{{ __('structure.common.confirm_delete', ['name' => $field->name]) }}" data-confirm-label="{{ __('app.actions.delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endif
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="settings" :title="__('employees.custom_fields.empty')" />
        @endforelse
    </x-table>
@endsection
