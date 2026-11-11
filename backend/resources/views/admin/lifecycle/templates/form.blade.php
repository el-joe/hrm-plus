@extends('layouts.admin', ['breadcrumbs' => [['label' => __('onboarding.templates.title'), 'url' => route('admin.onboarding-templates.index')]]])

@section('title', $template->exists ? __('onboarding.templates.edit') : __('onboarding.templates.create'))

@section('content')
    <form method="POST" action="{{ $template->exists ? route('admin.onboarding-templates.update', $template) : route('admin.onboarding-templates.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($template->exists)
            @method('PUT')
        @endif
        <x-form.section :title="__('onboarding.templates.section')" :description="__('onboarding.templates.section_help')">
            <x-form.input name="name_ar" :label="__('onboarding.fields.name_ar')" :value="old('name_ar', $template->name_ar)" required maxlength="150" dir="rtl" />
            <x-form.input name="name_en" :label="__('onboarding.fields.name_en')" :value="old('name_en', $template->name_en)" required maxlength="150" dir="ltr" />
            <div class="md:col-span-2">
                <x-form.textarea name="description" :label="__('app.common.description')" rows="2" maxlength="1000">{{ old('description', $template->description) }}</x-form.textarea>
            </div>
        </x-form.section>
        <x-form.section :title="__('onboarding.templates.applies_to')" :description="__('onboarding.templates.matching_help')">
            <x-form.select name="department_id" :label="__('onboarding.fields.department_id')">
                <option value="">{{ __('onboarding.templates.any') }}</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) old('department_id', $template->department_id) === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="position_id" :label="__('onboarding.fields.position_id')">
                <option value="">{{ __('onboarding.templates.any') }}</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected((string) old('position_id', $template->position_id) === (string) $position->id)>{{ $position->code }} · {{ $position->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="employment_type_id" :label="__('onboarding.fields.employment_type_id')">
                <option value="">{{ __('onboarding.templates.any') }}</option>
                @foreach ($employmentTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) old('employment_type_id', $template->employment_type_id) === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
            </x-form.select>
            <div class="flex items-end pb-2">
                <input type="hidden" name="is_active" value="0">
                <x-form.switch name="is_active" :label="__('onboarding.common.active')" :checked="(bool) old('is_active', $template->is_active)" />
            </div>
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="$template->exists ? route('admin.onboarding-templates.show', $template) : route('admin.onboarding-templates.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
