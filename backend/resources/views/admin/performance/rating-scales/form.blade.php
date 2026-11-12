@extends('layouts.admin', ['breadcrumbs' => [['label' => __('performance.scales.title'), 'url' => route('admin.rating-scales.index')]]])

@section('title', $record->exists ? __('performance.scales.edit') : __('performance.scales.create'))

@section('content')
    @php
        $rows = old('levels', $levels);
        $rows = [...$rows, ...array_fill(0, $locked ? 0 : max(0, 5 - count($rows)), [])];
    @endphp
    @if ($locked)
        <x-alert tone="info">{{ __('performance.scales.locked_help') }}</x-alert>
    @endif
    <form method="POST" action="{{ $record->exists ? route('admin.rating-scales.update', $record) : route('admin.rating-scales.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('performance.scales.details')">
            @include('admin.structure._identity-fields')
            <div>
                <input type="hidden" name="is_active" value="0">
                <x-form.switch name="is_active" :label="__('structure.common.active')" :checked="(bool) old('is_active', $record->is_active)" />
            </div>
            @include('admin.structure._description-field')
        </x-form.section>

        <x-form.section :title="__('performance.scales.levels')" :description="__('performance.scales.levels_help')">
            <div class="md:col-span-2 flex flex-col gap-3" data-repeater="levels">
                @error('levels')
                    <x-alert tone="danger">{{ $message }}</x-alert>
                @enderror
                <div class="flex flex-col gap-3" data-repeater-rows>
                    @foreach ($rows as $index => $row)
                        @include('admin.performance.rating-scales._level', ['index' => $index, 'row' => $row])
                    @endforeach
                </div>
                @unless ($locked)
                    <template data-repeater-template>
                        @include('admin.performance.rating-scales._level', ['index' => '__INDEX__', 'row' => []])
                    </template>
                    <div>
                        <x-button type="button" variant="secondary" size="sm" icon="plus" data-repeater-add hidden data-js-only>{{ __('performance.scales.add_level') }}</x-button>
                    </div>
                @endunless
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.rating-scales.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
