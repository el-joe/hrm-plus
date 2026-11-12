@extends('layouts.admin', [
    'subtitle' => __('dashboard.customise.subtitle'),
])

@section('title', __('dashboard.customise.title'))

@section('content')
    <form method="POST" action="{{ route('admin.dashboard.customise.update') }}" class="flex flex-col gap-4 lg:gap-6" data-dashboard-layout>
        @csrf
        @method('PUT')

        <x-card :title="__('dashboard.customise.widgets')" :subtitle="__('dashboard.customise.help')" padding="none">
            @if ($widgets === [])
                <x-empty-state icon="layout-grid" :title="__('dashboard.empty.no_permitted_widgets')" />
            @else
                <ol class="divide-y divide-border-subtle border-t border-border-subtle" data-layout-list>
                    @foreach ($widgets as $index => $key)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3" data-layout-item="{{ $key }}">
                            <div class="min-w-0 flex-1">
                                <x-form.checkbox name="visible[]" :value="$key" :checked="! in_array($key, $hidden, true)" :label="__('dashboard.widgets.'.$key.'.title')" id="visible-{{ $key }}" />
                            </div>
                            <label class="flex items-center gap-2 text-caption-md text-text-secondary" for="position-{{ $key }}">
                                {{ __('dashboard.customise.position') }}
                                <input type="number" min="0" max="999" name="position[{{ $key }}]" id="position-{{ $key }}" value="{{ $index + 1 }}" data-layout-position
                                    class="h-9 w-16 rounded-md border border-border-default bg-surface-default px-2 text-body-sm text-text-primary tabular-nums">
                            </label>
                            <span class="flex gap-1" hidden data-js-only>
                                <x-icon-button icon="arrow-up" :label="__('dashboard.customise.move_up')" type="button" size="sm" data-layout-move="up" />
                                <x-icon-button icon="arrow-down" :label="__('dashboard.customise.move_down')" type="button" size="sm" data-layout-move="down" />
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-card>

        <x-form.actions>
            <x-button variant="ghost" type="submit" name="reset" value="1">{{ __('dashboard.customise.reset') }}</x-button>
            <x-button variant="secondary" :href="route('admin.dashboard')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
