@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('hr_requests.queue.title'), 'url' => route('admin.hr-requests.index')],
    ['label' => __('hr_requests.categories.title'), 'url' => route('admin.hr-request-categories.index')],
]])

@section('title', $category->exists ? __('hr_requests.categories.edit') : __('hr_requests.categories.new'))

@php
    $fieldRows = old('fields', $category->exists ? $category->fields->map(fn ($field) => [
        'key' => $field->key, 'label_ar' => $field->label_ar, 'label_en' => $field->label_en, 'type' => $field->type->value,
        'options' => implode("\n", $field->options ?? []), 'is_required' => $field->is_required,
    ])->all() : []);
    $fieldRows = array_values($fieldRows) ?: [[]];
@endphp

@section('content')
    <form method="POST" action="{{ $category->exists ? route('admin.hr-request-categories.update', $category) : route('admin.hr-request-categories.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($category->exists) @method('PUT') @endif

        <x-form.section :title="__('hr_requests.categories.identity')" :description="__('hr_requests.categories.identity_help')">
            <x-form.input name="code" :label="__('hr_requests.category_fields.code')" required maxlength="30" dir="ltr" :value="old('code', $category->code)" />
            <x-form.input type="number" name="sort_order" :label="__('hr_requests.category_fields.sort_order')" min="0" max="999" :value="old('sort_order', $category->sort_order ?? 0)" />
            <x-form.input name="name_ar" :label="__('hr_requests.category_fields.name_ar')" required maxlength="150" dir="rtl" :value="old('name_ar', $category->name_ar)" />
            <x-form.input name="name_en" :label="__('hr_requests.category_fields.name_en')" required maxlength="150" dir="ltr" :value="old('name_en', $category->name_en)" />
            <x-form.textarea name="description_ar" :label="__('hr_requests.category_fields.description_ar')" rows="2" dir="rtl" maxlength="1000">{{ old('description_ar', $category->description_ar) }}</x-form.textarea>
            <x-form.textarea name="description_en" :label="__('hr_requests.category_fields.description_en')" rows="2" dir="ltr" maxlength="1000">{{ old('description_en', $category->description_en) }}</x-form.textarea>
            <x-form.switch name="is_active" :label="__('hr_requests.category_fields.is_active')" :checked="(bool) old('is_active', $category->is_active)" />
        </x-form.section>

        <x-form.section :title="__('hr_requests.categories.settings')" :description="__('hr_requests.categories.settings_help')">
            <x-form.select name="type" :label="__('hr_requests.category_fields.type')" required data-switch="category-type">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $category->type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <div data-switch-group="category-type" data-show-for="letter">
                <x-form.select name="hr_letter_template_id" :label="__('hr_requests.category_fields.hr_letter_template_id')">
                    <option value="">—</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}" @selected((string) old('hr_letter_template_id', $category->hr_letter_template_id) === (string) $template->id)>{{ $template->code }} · {{ $template->name }}</option>
                    @endforeach
                </x-form.select>
            </div>
            <x-form.input type="number" name="sla_hours" :label="__('hr_requests.category_fields.sla_hours')" min="1" max="2160"
                :help="__('hr_requests.categories.sla_default', ['hours' => setting('hr_requests.default_sla_hours')])" :value="old('sla_hours', $category->sla_hours)" />
            <x-form.select name="default_priority" :label="__('hr_requests.category_fields.default_priority')" required>
                @foreach ($priorities as $priority)
                    <option value="{{ $priority->value }}" @selected(old('default_priority', $category->default_priority?->value) === $priority->value)>{{ $priority->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="default_assignee_id" :label="__('hr_requests.category_fields.default_assignee_id')">
                <option value="">{{ __('hr_requests.common.unassigned') }}</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected((string) old('default_assignee_id', $category->default_assignee_id) === (string) $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="escalation_user_id" :label="__('hr_requests.category_fields.escalation_user_id')">
                <option value="">—</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected((string) old('escalation_user_id', $category->escalation_user_id) === (string) $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.switch name="requires_approval" :label="__('hr_requests.category_fields.requires_approval')" :checked="(bool) old('requires_approval', $category->requires_approval)" />
            <x-form.switch name="requires_attachment" :label="__('hr_requests.category_fields.requires_attachment')" :checked="(bool) old('requires_attachment', $category->requires_attachment)" />
        </x-form.section>

        <x-form.section :title="__('hr_requests.categories.fields_title')" :description="__('hr_requests.categories.fields_help')" :columns="1">
            <div class="flex flex-col gap-3" data-repeater="fields">
                <div class="flex flex-col gap-3" data-repeater-rows>
                    @foreach ($fieldRows as $index => $field)
                        @include('admin.hr-requests.categories._field', ['index' => $index, 'field' => $field])
                    @endforeach
                </div>
                <template data-repeater-template>
                    @include('admin.hr-requests.categories._field', ['index' => '__INDEX__', 'field' => []])
                </template>
                <div><x-button type="button" variant="secondary" size="sm" icon="plus" data-repeater-add hidden data-js-only>{{ __('hr_requests.categories.add_field') }}</x-button></div>
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.hr-request-categories.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
