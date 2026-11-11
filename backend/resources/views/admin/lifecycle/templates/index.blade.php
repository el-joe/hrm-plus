@extends('layouts.admin', ['subtitle' => __('onboarding.templates.subtitle')])

@section('title', __('onboarding.templates.title'))

@section('page-actions')
    <x-button :href="route('admin.onboarding-templates.create')" icon="plus">{{ __('onboarding.templates.create') }}</x-button>
@endsection

@section('content')
    <x-alert tone="info">{{ __('onboarding.templates.matching_help') }}</x-alert>
    <x-table :caption="__('onboarding.templates.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('onboarding.fields.name') }}</x-table.th>
                <x-table.th>{{ __('onboarding.templates.applies_to') }}</x-table.th>
                <x-table.th>{{ __('onboarding.templates.tasks') }}</x-table.th>
                <x-table.th>{{ __('onboarding.templates.used') }}</x-table.th>
                <x-table.th>{{ __('app.common.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($templates as $template)
            <x-table.row :data-href="route('admin.onboarding-templates.show', $template)">
                <x-table.td :label="__('onboarding.fields.name')"><a href="{{ route('admin.onboarding-templates.show', $template) }}" class="text-label-md text-text-primary hover:text-text-link">{{ $template->name }}</a></x-table.td>
                <x-table.td :label="__('onboarding.templates.applies_to')" class="text-text-secondary">
                    {{ collect([$template->position?->name, $template->department?->name, $template->employmentType?->name])->filter()->implode(' · ') ?: __('onboarding.templates.everyone') }}
                </x-table.td>
                <x-table.td :label="__('onboarding.templates.tasks')" class="tabular-nums">{{ $template->tasks_count }}</x-table.td>
                <x-table.td :label="__('onboarding.templates.used')" class="tabular-nums">{{ $template->onboardings_count }}</x-table.td>
                <x-table.td :label="__('app.common.status')"><x-badge :tone="$template->is_active ? 'success' : 'neutral'">{{ $template->is_active ? __('onboarding.common.active') : __('onboarding.common.inactive') }}</x-badge></x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="file-text" :title="__('onboarding.templates.empty')" />
        @endforelse
    </x-table>
@endsection
