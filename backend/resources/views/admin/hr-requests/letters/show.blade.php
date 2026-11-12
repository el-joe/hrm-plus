@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('hr_requests.queue.title'), 'url' => route('admin.hr-requests.index')],
    ['label' => __('hr_requests.letters.title'), 'url' => route('admin.hr-letter-templates.index')],
]])

@section('title', $template->code.' · '.$template->name)

@section('page-actions')
    @can('update', $template)
        <x-button variant="secondary" icon="pencil" :href="route('admin.hr-letter-templates.edit', $template)">{{ __('app.actions.edit') }}</x-button>
    @endcan
@endsection

@section('content')
    @unless ($template->is_active)
        <x-alert tone="warning">{{ __('structure.common.inactive') }}</x-alert>
    @endunless
    @if ($usesSalary)
        <x-alert tone="info">{{ __('hr_requests.letters.salary_notice') }}</x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
        @foreach ($previews as $locale => $text)
            <x-card :title="__('hr_requests.letters.preview').' — '.__('app.locales.'.$locale)" data-preview="{{ $locale }}">
                <article dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}" lang="{{ $locale }}" class="flex flex-col gap-3 rounded-lg border border-border-subtle bg-surface-default p-4 text-body-md text-text-primary">
                    <p class="text-center text-h5">{{ $template->localized('name', $locale) }}</p>
                    @foreach (preg_split('/\R{2,}/', $text) as $paragraph)
                        <p class="whitespace-pre-line">{{ trim($paragraph) }}</p>
                    @endforeach
                </article>
            </x-card>
        @endforeach
    </div>

    <x-card :title="__('hr_requests.letters.placeholders')" :subtitle="__('hr_requests.letters.placeholders_hint')">
        @include('admin.hr-requests.letters._placeholders', ['placeholders' => App\Services\HrRequests\HrLetterDocument::PLACEHOLDERS])
    </x-card>
@endsection
