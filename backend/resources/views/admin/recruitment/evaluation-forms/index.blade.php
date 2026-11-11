@extends('layouts.admin', ['subtitle' => __('recruitment.forms.subtitle')])

@section('title', __('recruitment.forms.title'))

@section('page-actions')
    <x-button :href="route('admin.evaluation-forms.create')" icon="plus">{{ __('recruitment.forms.create') }}</x-button>
@endsection

@section('content')
    <x-table :caption="__('recruitment.forms.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('recruitment.fields.name') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.criteria') }}</x-table.th>
                <x-table.th>{{ __('recruitment.forms.used_by') }}</x-table.th>
                <x-table.th>{{ __('app.common.status') }}</x-table.th>
                <x-table.th class="text-end">{{ __('app.common.actions') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($forms as $form)
            <x-table.row>
                <x-table.td :label="__('recruitment.fields.name')">
                    <span class="block text-label-md">{{ $form->name }}</span>
                    @if ($form->description)
                        <span class="text-caption-md text-text-tertiary">{{ $form->description }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('recruitment.fields.criteria')" class="tabular-nums">{{ $form->criteria_count }}</x-table.td>
                <x-table.td :label="__('recruitment.forms.used_by')" class="tabular-nums">{{ trans_choice('recruitment.pipeline.interviews_count', $form->interviews_count, ['count' => $form->interviews_count]) }}</x-table.td>
                <x-table.td :label="__('app.common.status')"><x-badge :tone="$form->is_active ? 'success' : 'neutral'">{{ $form->is_active ? __('recruitment.common.active') : __('recruitment.common.inactive') }}</x-badge></x-table.td>
                <x-table.td :label="__('app.common.actions')" class="text-end">
                    <div class="flex justify-end gap-1">
                        <x-icon-button icon="pencil" :href="route('admin.evaluation-forms.edit', $form)" :label="__('app.actions.edit')" />
                        <form method="POST" action="{{ route('admin.evaluation-forms.destroy', $form) }}" data-confirm="{{ $form->interviews_count ? __('recruitment.forms.deactivate_confirm') : __('recruitment.forms.delete_confirm') }}">
                            @csrf
                            @method('DELETE')
                            <x-icon-button icon="trash" type="submit" :label="__('app.actions.delete')" />
                        </form>
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="award" :title="__('recruitment.forms.empty')" />
        @endforelse
    </x-table>
@endsection
