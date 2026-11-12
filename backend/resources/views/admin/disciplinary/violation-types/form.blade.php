@extends('layouts.admin', ['breadcrumbs' => [['label' => __('disciplinary.violation_types.title'), 'url' => route('admin.violation-types.index')]]])

@section('title', $record->exists ? __('disciplinary.violation_types.edit') : __('disciplinary.violation_types.create'))

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.violation-types.update', $record) : route('admin.violation-types.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('disciplinary.violation_types.identity')">
            @include('admin.structure._identity-fields')
            @include('admin.structure._description-field')
        </x-form.section>

        <x-form.section :title="__('disciplinary.violation_types.rules')" :description="__('disciplinary.violation_types.rules_help')">
            <x-form.select name="severity" :label="__('disciplinary.fields.severity')" required>
                @foreach ($severities as $severity)
                    <option value="{{ $severity->value }}" @selected(old('severity', $record->severity?->value) === $severity->value)>{{ $severity->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="default_action" :label="__('disciplinary.fields.default_action')" required>
                @foreach ($actionTypes as $type)
                    <option value="{{ $type->value }}" @selected(old('default_action', $record->default_action?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <div class="md:col-span-2">
                <input type="hidden" name="is_active" value="0">
                <x-form.switch name="is_active" :label="__('structure.common.active')" :checked="(bool) old('is_active', $record->is_active)" />
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.violation-types.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button icon="check">{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
