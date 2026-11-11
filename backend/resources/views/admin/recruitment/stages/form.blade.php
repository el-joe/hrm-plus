@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('recruitment.stages.title'), 'url' => route('admin.recruitment-stages.index', array_filter(['vacancy' => $vacancy?->id]))]],
    'subtitle' => $vacancy ? __('recruitment.stages.vacancy_subtitle', ['reference' => $vacancy->reference]) : __('recruitment.stages.subtitle'),
])

@section('title', $stage->exists ? __('recruitment.stages.edit') : __('recruitment.stages.create'))

@section('content')
    <form method="POST" action="{{ $stage->exists ? route('admin.recruitment-stages.update', $stage) : route('admin.recruitment-stages.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($stage->exists)
            @method('PUT')
        @elseif ($vacancy)
            <input type="hidden" name="vacancy_id" value="{{ $vacancy->id }}">
        @endif
        <x-form.section :title="__('recruitment.stages.section')" :description="__('recruitment.stages.section_help')">
            <x-form.input name="name_ar" :label="__('recruitment.fields.name_ar')" :value="old('name_ar', $stage->name_ar)" required maxlength="100" dir="rtl" />
            <x-form.input name="name_en" :label="__('recruitment.fields.name_en')" :value="old('name_en', $stage->name_en)" required maxlength="100" dir="ltr" />
            <x-form.input name="code" :label="__('recruitment.fields.code')" :value="old('code', $stage->code)" required maxlength="40" dir="ltr" pattern="[a-z0-9_]+" :help="__('recruitment.stages.code_help')" />
            <x-form.select name="category" :label="__('recruitment.fields.category')" required :help="__('recruitment.stages.category_help')">
                @foreach ($categories as $category)
                    <option value="{{ $category->value }}" @selected(old('category', $stage->category?->value) === $category->value)>{{ $category->label() }}</option>
                @endforeach
            </x-form.select>
            <div class="flex flex-col gap-3 md:col-span-2">
                <input type="hidden" name="notify_candidate" value="0">
                <x-form.switch name="notify_candidate" :label="__('recruitment.fields.notify_candidate')" :description="__('recruitment.stages.notify_help')" :checked="(bool) old('notify_candidate', $stage->notify_candidate)" />
                <input type="hidden" name="is_active" value="0">
                <x-form.switch name="is_active" :label="__('recruitment.common.active')" :checked="(bool) old('is_active', $stage->is_active ?? true)" />
            </div>
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.recruitment-stages.index', array_filter(['vacancy' => $vacancy?->id]))">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
