@extends('layouts.admin', ['breadcrumbs' => [['label' => __('payroll.statutory.title'), 'url' => route('admin.statutory-rules.index')]]])

@section('title', $record->exists ? __('payroll.statutory.edit') : __('payroll.statutory.create'))

@section('content')
    @php($locked = $record->exists && $record->versions()->exists())
    <form method="POST" action="{{ $record->exists ? route('admin.statutory-rules.update', $record) : route('admin.statutory-rules.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('payroll.statutory.identity')" :description="$locked ? __('payroll.statutory.locked') : null">
            @include('admin.structure._identity-fields')
            <x-form.input name="country_code" :label="__('payroll.fields.country_code')" :value="old('country_code', $record->country_code)" maxlength="2" dir="ltr" class="uppercase"
                :required="! $locked" :disabled="$locked" />
            <x-form.select name="type" :label="__('payroll.fields.type')" :required="! $locked" :disabled="$locked">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $record->type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            @include('admin.structure._description-field')
            <div class="md:col-span-2">
                <input type="hidden" name="is_demo" value="0">
                <x-form.switch name="is_demo" :label="__('payroll.fields.is_demo')" :description="__('payroll.common.demo_help')" :checked="(bool) old('is_demo', $record->is_demo)" />
            </div>
            @include('admin.structure._active-switch')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="$record->exists ? route('admin.statutory-rules.show', $record) : route('admin.statutory-rules.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
