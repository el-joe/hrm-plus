@extends('layouts.admin', ['breadcrumbs' => [['label' => __('schedules.statuses.title'), 'url' => route('admin.attendance-statuses.index')]]])

@section('title', $record->exists ? __('schedules.statuses.edit') : __('schedules.statuses.create'))

@section('content')
    <form method="POST" action="{{ $record->exists ? route('admin.attendance-statuses.update', $record) : route('admin.attendance-statuses.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('structure.common.identity')">
            <x-form.input name="code" :label="__('schedules.fields.code')" :value="old('code', $record->code)" required maxlength="30" dir="ltr"
                pattern="[a-z][a-z0-9_]*" :disabled="$record->is_system" :help="$record->is_system ? __('schedules.statuses.system_code') : null" />
            <x-form.input type="number" name="sort_order" :label="__('schedules.fields.sort_order')" :value="old('sort_order', $record->sort_order)" required min="0" max="9999" />
            <x-form.input name="name_ar" :label="__('schedules.fields.name_ar')" :value="old('name_ar', $record->name_ar)" required maxlength="150" dir="rtl" lang="ar" />
            <x-form.input name="name_en" :label="__('schedules.fields.name_en')" :value="old('name_en', $record->name_en)" required maxlength="150" dir="ltr" lang="en" />
        </x-form.section>

        <x-form.section :title="__('schedules.fields.category')" :description="__('schedules.statuses.meaning_help')">
            <x-form.select name="category" :label="__('schedules.fields.category')" required class="md:col-span-2">
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $record->category?->value) === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </x-form.select>
            <div>
                <input type="hidden" name="counts_as_worked" value="0">
                <x-form.switch name="counts_as_worked" :label="__('schedules.fields.counts_as_worked')" :description="__('schedules.statuses.counts_as_worked_help')"
                    :checked="(bool) old('counts_as_worked', $record->counts_as_worked)" />
            </div>
            <div>
                <input type="hidden" name="is_paid" value="0">
                <x-form.switch name="is_paid" :label="__('schedules.fields.is_paid')" :checked="(bool) old('is_paid', $record->is_paid)" />
            </div>
            @include('admin.structure._active-switch')
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.attendance-statuses.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
