@extends('layouts.admin', ['breadcrumbs' => [['label' => __('recruitment.forms.title'), 'url' => route('admin.evaluation-forms.index')]]])

@section('title', $form->exists ? __('recruitment.forms.edit') : __('recruitment.forms.create'))

@section('content')
    @php
        $rows = old('criteria', $form->criteria?->map->only(['id', 'name_ar', 'name_en', 'description', 'weight'])->all() ?? []);
        $rows = array_pad(array_values($rows), max(count($rows) + 3, 5), ['id' => null, 'name_ar' => '', 'name_en' => '', 'description' => '', 'weight' => 1]);
    @endphp
    <form method="POST" action="{{ $form->exists ? route('admin.evaluation-forms.update', $form) : route('admin.evaluation-forms.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($form->exists)
            @method('PUT')
        @endif
        <x-form.section :title="__('recruitment.forms.section')" :description="__('recruitment.forms.section_help', ['scale' => config('recruitment.score_scale')])">
            <x-form.input name="name_ar" :label="__('recruitment.fields.name_ar')" :value="old('name_ar', $form->name_ar)" required maxlength="150" dir="rtl" />
            <x-form.input name="name_en" :label="__('recruitment.fields.name_en')" :value="old('name_en', $form->name_en)" required maxlength="150" dir="ltr" />
            <div class="md:col-span-2">
                <x-form.textarea name="description" :label="__('app.common.description')" rows="2" maxlength="1000">{{ old('description', $form->description) }}</x-form.textarea>
            </div>
            <div class="md:col-span-2">
                <input type="hidden" name="is_active" value="0">
                <x-form.switch name="is_active" :label="__('recruitment.common.active')" :checked="(bool) old('is_active', $form->is_active)" />
            </div>
        </x-form.section>

        <x-form.section :title="__('recruitment.fields.criteria')" :description="__('recruitment.forms.criteria_help')" :columns="1">
            @error('criteria')<x-alert tone="danger">{{ $message }}</x-alert>@enderror
            <div class="flex flex-col gap-3">
                @foreach ($rows as $index => $row)
                    <fieldset class="grid gap-3 rounded-lg border border-border-subtle p-3 md:grid-cols-[1fr_1fr_1fr_6rem]">
                        <legend class="sr-only">{{ __('recruitment.forms.criterion', ['number' => $index + 1]) }}</legend>
                        <input type="hidden" name="criteria[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
                        <x-form.input name="criteria[{{ $index }}][name_ar]" :label="__('recruitment.fields.name_ar')" :value="$row['name_ar'] ?? ''" maxlength="150" dir="rtl" />
                        <x-form.input name="criteria[{{ $index }}][name_en]" :label="__('recruitment.fields.name_en')" :value="$row['name_en'] ?? ''" maxlength="150" dir="ltr" />
                        <x-form.input name="criteria[{{ $index }}][description]" :label="__('app.common.description')" :value="$row['description'] ?? ''" maxlength="500" />
                        <x-form.input name="criteria[{{ $index }}][weight]" type="number" min="1" max="10" :label="__('recruitment.fields.weight')" :value="$row['weight'] ?? 1" />
                    </fieldset>
                @endforeach
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.evaluation-forms.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
