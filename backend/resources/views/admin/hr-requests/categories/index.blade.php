@extends('layouts.admin', ['breadcrumbs' => [['label' => __('hr_requests.queue.title'), 'url' => route('admin.hr-requests.index')]]])

@section('title', __('hr_requests.categories.title'))

@section('page-actions')
    <x-button icon="plus" :href="route('admin.hr-request-categories.create')">{{ __('hr_requests.categories.new') }}</x-button>
@endsection

@section('content')
    @error('record') <x-alert tone="danger">{{ $message }}</x-alert> @enderror

    <x-table :caption="__('hr_requests.categories.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('hr_requests.category_fields.code') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('hr_requests.fields.category') }}</x-table.th>
                <x-table.th>{{ __('hr_requests.category_fields.type') }}</x-table.th>
                <x-table.th>{{ __('hr_requests.category_fields.sla_hours') }}</x-table.th>
                <x-table.th>{{ __('hr_requests.category_fields.default_assignee_id') }}</x-table.th>
                <x-table.th>{{ __('app.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($categories as $category)
            <x-table.row>
                <x-table.td :label="__('hr_requests.category_fields.code')"><span dir="ltr" class="text-label-md">{{ $category->code }}</span></x-table.td>
                <x-table.td :label="__('hr_requests.fields.category')">
                    <span class="block text-label-md">{{ $category->name }}</span>
                    <span class="flex flex-wrap gap-1 text-caption-md text-text-tertiary">
                        {{ trans_choice('hr_requests.categories.requests_count', $category->requests_count, ['count' => $category->requests_count]) }}
                        @if ($category->requires_approval) <x-badge tone="warning">{{ __('hr_requests.categories.needs_approval') }}</x-badge> @endif
                        @if ($category->requires_attachment) <x-badge>{{ __('hr_requests.categories.needs_attachment') }}</x-badge> @endif
                    </span>
                </x-table.td>
                <x-table.td :label="__('hr_requests.category_fields.type')">
                    {{ $category->type->label() }}
                    @if ($category->letterTemplate) <span class="block text-caption-md text-text-tertiary">{{ $category->letterTemplate->name }}</span> @endif
                </x-table.td>
                <x-table.td :label="__('hr_requests.category_fields.sla_hours')"><span dir="ltr">{{ $category->slaHours() }} h</span></x-table.td>
                <x-table.td :label="__('hr_requests.category_fields.default_assignee_id')">{{ $category->defaultAssignee?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('app.common.status')">
                    <x-badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? __('structure.common.active') : __('structure.common.inactive') }}</x-badge>
                </x-table.td>
                <x-table.td class="text-end">
                    <div class="flex justify-end gap-1">
                        <x-icon-button icon="pencil" :href="route('admin.hr-request-categories.edit', $category)" :label="__('app.actions.edit')" />
                        @if ($category->requests_count === 0)
                            <form method="POST" action="{{ route('admin.hr-request-categories.destroy', $category) }}" data-confirm="{{ __('structure.common.confirm_delete', ['name' => $category->name]) }}" data-confirm-tone="danger">
                                @csrf @method('DELETE')
                                <x-icon-button type="submit" icon="trash" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endif
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="layers" :title="__('hr_requests.categories.empty')" />
        @endforelse
    </x-table>
@endsection
