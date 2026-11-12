@extends('layouts.admin', ['subtitle' => __('notifications.templates.subtitle')])

@section('title', __('notifications.templates.title'))

@section('content')
    @cannot('notifications.manage')
        <x-alert tone="info">{{ __('notifications.templates.read_only') }}</x-alert>
    @endcannot

    <x-filter-bar :action="route('admin.notification-templates.index')" :reset="route('admin.notification-templates.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('notifications.templates.filters.search')" hide-label
            :placeholder="__('notifications.templates.filters.search')" :value="$filters['search'] ?? ''" class="md:w-64" data-filter-debounce />
        <x-form.select name="module" :label="__('notifications.templates.filters.module')" hide-label class="md:w-56">
            <option value="">{{ __('notifications.templates.module') }}: {{ __('app.common.all') }}</option>
            @foreach ($modules as $module)
                <option value="{{ $module }}" @selected(($filters['module'] ?? '') === $module)>{{ __('notifications.modules.'.$module) }}</option>
            @endforeach
        </x-form.select>
        <div class="flex h-10 items-center">
            <x-form.checkbox name="customised" value="1" id="filter-customised" :label="__('notifications.templates.filters.customised').' ('.$customisedCount.')'" :checked="! empty($filters['customised'])" />
        </div>
    </x-filter-bar>

    @forelse ($groups as $module => $rows)
        <x-table :caption="__('notifications.modules.'.$module)" data-module="{{ $module }}">
            <x-slot:head>
                <tr>
                    <x-table.th class="min-w-64">{{ __('notifications.modules.'.$module) }}</x-table.th>
                    <x-table.th>{{ __('notifications.templates.audience') }}</x-table.th>
                    <x-table.th>{{ __('app.common.status') }}</x-table.th>
                    <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($rows as $row)
                <x-table.row :data-href="route('admin.notification-templates.edit', $row['key'])">
                    <x-table.td :label="__('notifications.templates.event')">
                        <span class="block text-label-md text-text-primary">{{ $row['label'] }}</span>
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $row['key'] }}</span>
                    </x-table.td>
                    <x-table.td :label="__('notifications.templates.audience')">{{ __('notifications.templates.audiences.'.$row['audience']) }}</x-table.td>
                    <x-table.td :label="__('app.common.status')">
                        <div class="flex flex-wrap gap-1">
                            <x-badge :tone="$row['customised'] ? 'primary' : 'neutral'">{{ $row['customised'] ? __('notifications.templates.customised') : __('notifications.templates.default') }}</x-badge>
                            @if ($row['mandatory'])
                                <x-badge tone="warning">{{ __('notifications.templates.mandatory') }}</x-badge>
                            @endif
                        </div>
                    </x-table.td>
                    <x-table.td class="text-end">
                        <x-button size="sm" variant="secondary" :href="route('admin.notification-templates.edit', $row['key'])" icon="pencil">{{ __('notifications.templates.edit') }}</x-button>
                    </x-table.td>
                </x-table.row>
            @endforeach
        </x-table>
    @empty
        <x-card><x-empty-state icon="mail" :title="__('notifications.templates.empty')" /></x-card>
    @endforelse
@endsection
