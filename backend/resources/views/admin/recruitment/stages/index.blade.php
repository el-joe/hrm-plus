@extends('layouts.admin', [
    'breadcrumbs' => $vacancy
        ? [['label' => __('recruitment.vacancies.title'), 'url' => route('admin.vacancies.index')], ['label' => $vacancy->title, 'url' => route('admin.vacancies.show', $vacancy)]]
        : [],
    'subtitle' => $vacancy ? __('recruitment.stages.vacancy_subtitle', ['reference' => $vacancy->reference]) : __('recruitment.stages.subtitle'),
])

@section('title', __('recruitment.stages.title'))

@section('page-actions')
    <x-button :href="route('admin.recruitment-stages.create', array_filter(['vacancy' => $vacancy?->id]))" icon="plus">{{ __('recruitment.stages.create') }}</x-button>
@endsection

@section('content')
    <x-alert tone="info">{{ __('recruitment.stages.categories_help') }}</x-alert>

    <x-table :caption="__('recruitment.stages.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="w-24">{{ __('recruitment.stages.order') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('recruitment.fields.name') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.category') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.notify_candidate') }}</x-table.th>
                <x-table.th>{{ __('recruitment.stages.applications') }}</x-table.th>
                <x-table.th>{{ __('app.common.status') }}</x-table.th>
                <x-table.th class="text-end">{{ __('app.common.actions') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($stages as $stage)
            <x-table.row>
                <x-table.td :label="__('recruitment.stages.order')">
                    <div class="flex gap-1">
                        @foreach (['up' => 'arrow-up', 'down' => 'arrow-down'] as $direction => $icon)
                            <form method="POST" action="{{ route('admin.recruitment-stages.move', $stage) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="direction" value="{{ $direction }}">
                                <x-icon-button :icon="$icon" type="submit" size="sm" :label="__('recruitment.stages.move_'.$direction, ['name' => $stage->name])"
                                    :disabled="($direction === 'up' && $loop->parent->first) || ($direction === 'down' && $loop->parent->last)" />
                            </form>
                        @endforeach
                    </div>
                </x-table.td>
                <x-table.td :label="__('recruitment.fields.name')">
                    <span class="block text-label-md">{{ $stage->name }}</span>
                    <span class="text-caption-md text-text-tertiary" dir="ltr">{{ $stage->code }}</span>
                </x-table.td>
                <x-table.td :label="__('recruitment.fields.category')"><x-badge :tone="$stage->category->tone()">{{ $stage->category->label() }}</x-badge></x-table.td>
                <x-table.td :label="__('recruitment.fields.notify_candidate')">{{ $stage->notify_candidate ? __('app.common.yes') : __('app.common.no') }}</x-table.td>
                <x-table.td :label="__('recruitment.stages.applications')" class="tabular-nums">{{ $stage->applications_count }}</x-table.td>
                <x-table.td :label="__('app.common.status')">
                    <x-badge :tone="$stage->is_active ? 'success' : 'neutral'">{{ $stage->is_active ? __('recruitment.common.active') : __('recruitment.common.inactive') }}</x-badge>
                </x-table.td>
                <x-table.td :label="__('app.common.actions')" class="text-end">
                    <div class="flex justify-end gap-1">
                        <x-icon-button icon="pencil" :href="route('admin.recruitment-stages.edit', $stage)" :label="__('app.actions.edit')" />
                        @if ($stage->applications_count === 0)
                            <form method="POST" action="{{ route('admin.recruitment-stages.destroy', $stage) }}" data-confirm="{{ __('recruitment.stages.delete_confirm', ['name' => $stage->name]) }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button icon="trash" type="submit" :label="__('app.actions.delete')" />
                            </form>
                        @endif
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="layers" :title="__('recruitment.stages.empty')" />
        @endforelse
    </x-table>
@endsection
