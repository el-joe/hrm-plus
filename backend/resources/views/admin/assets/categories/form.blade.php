@extends('layouts.admin', ['breadcrumbs' => [['label' => __('assets.categories.title'), 'url' => route('admin.asset-categories.index')]]])

@section('title', $category->exists ? __('assets.categories.edit') : __('assets.categories.create'))

@section('content')
    <form method="POST" action="{{ $category->exists ? route('admin.asset-categories.update', $category) : route('admin.asset-categories.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($category->exists)
            @method('PUT')
        @endif
        <x-form.section :title="__('assets.categories.title')">
            <x-form.input name="code" :label="__('assets.categories.fields.code')" :value="old('code', $category->code)" required maxlength="20" dir="ltr" class="uppercase" pattern="[A-Za-z0-9][A-Za-z0-9._\-]*" />
            <div class="hidden md:block" aria-hidden="true"></div>
            <x-form.input name="name_ar" :label="__('assets.categories.fields.name_ar')" :value="old('name_ar', $category->name_ar)" required maxlength="150" dir="rtl" lang="ar" />
            <x-form.input name="name_en" :label="__('assets.categories.fields.name_en')" :value="old('name_en', $category->name_en)" required maxlength="150" dir="ltr" lang="en" />
            <div class="md:col-span-2">
                <x-form.textarea name="description" :label="__('assets.categories.fields.description')" rows="3" maxlength="1000">{{ old('description', $category->description) }}</x-form.textarea>
            </div>
            <x-form.switch name="requires_serial" :label="__('assets.categories.fields.requires_serial')" :checked="(bool) old('requires_serial', $category->requires_serial)" />
            <x-form.switch name="is_active" :label="__('assets.categories.fields.is_active')" :checked="(bool) old('is_active', $category->is_active)" />
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.asset-categories.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
