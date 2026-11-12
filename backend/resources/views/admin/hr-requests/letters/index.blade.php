@extends('layouts.admin', ['breadcrumbs' => [['label' => __('hr_requests.queue.title'), 'url' => route('admin.hr-requests.index')]]])

@section('title', __('hr_requests.letters.title'))

@section('page-actions')
    <x-button icon="plus" :href="route('admin.hr-letter-templates.create')">{{ __('hr_requests.letters.new') }}</x-button>
@endsection

@section('content')
    @error('record') <x-alert tone="danger">{{ $message }}</x-alert> @enderror

    <x-table :caption="__('hr_requests.letters.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('hr_requests.letter_fields.code') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('app.common.name') }}</x-table.th>
                <x-table.th>{{ __('hr_requests.letters.categories') }}</x-table.th>
                <x-table.th>{{ __('app.common.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($templates as $template)
            <x-table.row :data-href="route('admin.hr-letter-templates.show', $template)">
                <x-table.td :label="__('hr_requests.letter_fields.code')"><span dir="ltr" class="text-label-md">{{ $template->code }}</span></x-table.td>
                <x-table.td :label="__('app.common.name')">
                    <a class="text-label-md text-text-link hover:underline" href="{{ route('admin.hr-letter-templates.show', $template) }}">{{ $template->name }}</a>
                </x-table.td>
                <x-table.td :label="__('hr_requests.letters.categories')">{{ $template->categories_count }}</x-table.td>
                <x-table.td :label="__('app.common.status')">
                    <x-badge :tone="$template->is_active ? 'success' : 'neutral'">{{ $template->is_active ? __('structure.common.active') : __('structure.common.inactive') }}</x-badge>
                </x-table.td>
                <x-table.td class="text-end">
                    <div class="flex justify-end gap-1">
                        <x-icon-button icon="pencil" :href="route('admin.hr-letter-templates.edit', $template)" :label="__('app.actions.edit')" />
                        @if ($template->categories_count === 0)
                            <form method="POST" action="{{ route('admin.hr-letter-templates.destroy', $template) }}" data-confirm="{{ __('structure.common.confirm_delete', ['name' => $template->name]) }}" data-confirm-tone="danger">
                                @csrf @method('DELETE')
                                <x-icon-button type="submit" icon="trash" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endif
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="file-text" :title="__('hr_requests.letters.empty')" />
        @endforelse
    </x-table>
@endsection
