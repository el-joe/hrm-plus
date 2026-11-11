@extends('layouts.admin', ['breadcrumbs' => [['label' => __('schedules.policies.title'), 'url' => route('admin.attendance-policies.index')]]])

@section('title', $record->exists ? __('schedules.policies.edit') : __('schedules.policies.create'))

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.attendance-policies.update', $record) : route('admin.attendance-policies.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
            @include('admin.structure._identity-fields')
            @include('admin.structure._description-field')
            @include('admin.structure._active-switch')
        </x-form.section>

        @if ($rules)
            <x-form.section :title="__('schedules.policies.version_n', ['n' => 1])" :description="__('schedules.policies.first_effective_help')">
                <x-form.input type="date" name="effective_from" :label="__('schedules.fields.effective_from')" :value="old('effective_from', $effectiveFrom)" required />
            </x-form.section>
            @include('admin.scheduling.policies._rule-fields')
        @endif

        <x-form.actions>
            <x-button variant="secondary" :href="$record->exists ? route('admin.attendance-policies.show', $record) : route('admin.attendance-policies.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
