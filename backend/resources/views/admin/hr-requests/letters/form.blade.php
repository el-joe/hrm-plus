@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('hr_requests.queue.title'), 'url' => route('admin.hr-requests.index')],
    ['label' => __('hr_requests.letters.title'), 'url' => route('admin.hr-letter-templates.index')],
]])

@section('title', $template->exists ? __('hr_requests.letters.edit') : __('hr_requests.letters.new'))

@section('content')
    <form method="POST" action="{{ $template->exists ? route('admin.hr-letter-templates.update', $template) : route('admin.hr-letter-templates.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($template->exists) @method('PUT') @endif

        <x-form.section :title="__('hr_requests.letters.title')">
            <x-form.input name="code" :label="__('hr_requests.letter_fields.code')" required maxlength="30" dir="ltr" :value="old('code', $template->code)" />
            <div class="flex items-end"><x-form.switch name="is_active" :label="__('hr_requests.letter_fields.is_active')" :checked="(bool) old('is_active', $template->is_active)" /></div>
            <x-form.input name="name_ar" :label="__('hr_requests.letter_fields.name_ar')" required maxlength="150" dir="rtl" :value="old('name_ar', $template->name_ar)" />
            <x-form.input name="name_en" :label="__('hr_requests.letter_fields.name_en')" required maxlength="150" dir="ltr" :value="old('name_en', $template->name_en)" />
            <div class="md:col-span-2"><x-form.textarea name="body_ar" :label="__('hr_requests.letter_fields.body_ar')" required rows="10" dir="rtl" maxlength="10000">{{ old('body_ar', $template->body_ar) }}</x-form.textarea></div>
            <div class="md:col-span-2"><x-form.textarea name="body_en" :label="__('hr_requests.letter_fields.body_en')" required rows="10" dir="ltr" maxlength="10000">{{ old('body_en', $template->body_en) }}</x-form.textarea></div>
        </x-form.section>

        <x-card :title="__('hr_requests.letters.placeholders')" :subtitle="__('hr_requests.letters.placeholders_hint')">
            @include('admin.hr-requests.letters._placeholders')
        </x-card>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.hr-letter-templates.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
