@extends('layouts.admin', ['breadcrumbs' => [['label' => __('employees.custom_fields.title'), 'url' => route('admin.employee-custom-fields.index')]]])

@section('title', $field->exists ? __('employees.custom_fields.edit') : __('employees.custom_fields.create'))

@section('content')
    <form method="POST" action="{{ $field->exists ? route('admin.employee-custom-fields.update', $field) : route('admin.employee-custom-fields.store') }}"
        class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($field->exists)
            @method('PUT')
        @endif
        <x-form.section :title="__('employees.custom_fields.definition')" :description="__('employees.custom_fields.definition_help')">
            <x-form.input name="code" :label="__('employees.custom_fields.fields.code')" :value="old('code', $field->code)" required maxlength="40" dir="ltr"
                :help="__('employees.custom_fields.code_help')" />
            <x-form.select name="type" :label="__('employees.custom_fields.fields.type')" required :help="$locked ? __('employees.custom_fields.type_locked') : null">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $field->type?->value) === $type->value) @disabled($locked && $field->type !== $type)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="name_ar" :label="__('employees.custom_fields.fields.name_ar')" :value="old('name_ar', $field->name_ar)" required maxlength="150" lang="ar" dir="rtl" />
            <x-form.input name="name_en" :label="__('employees.custom_fields.fields.name_en')" :value="old('name_en', $field->name_en)" required maxlength="150" lang="en" dir="ltr" />
            <div class="md:col-span-2">
                <x-form.textarea name="options" :label="__('employees.custom_fields.fields.options')" rows="4" maxlength="5000" dir="ltr"
                    :help="__('employees.custom_fields.options_help')">{{ old('options', $optionsText) }}</x-form.textarea>
            </div>
            <x-form.input name="sort_order" type="number" min="0" max="999" :label="__('employees.custom_fields.fields.sort_order')" :value="old('sort_order', $field->sort_order ?? 0)" />
            <div></div>
            @foreach (['is_required', 'visible_to_employee', 'is_active'] as $flag)
                <div>
                    <input type="hidden" name="{{ $flag }}" value="0">
                    <x-form.switch :name="$flag" :label="__('employees.custom_fields.fields.'.$flag)" :checked="(bool) old($flag, $field->{$flag})" />
                </div>
            @endforeach
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.employee-custom-fields.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
