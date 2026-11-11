@extends('layouts.public')

@section('title', $vacancy->title)

@section('content')
    <a href="{{ route('careers.index') }}" class="inline-flex items-center gap-1 text-label-md text-text-link"><x-icon name="arrow-left" size="sm" flip /> {{ __('recruitment.career.all_positions') }}</a>

    <div class="grid gap-6 lg:grid-cols-5">
        <article class="flex flex-col gap-5 lg:col-span-3">
            <header>
                <h1 class="text-h2">{{ $vacancy->title }}</h1>
                <p class="text-body-md text-text-secondary">{{ $vacancy->department?->name }} · {{ $vacancy->branch?->name }}@if ($vacancy->employmentType) · {{ $vacancy->employmentType->name }}@endif</p>
                <p class="text-caption-md text-text-tertiary"><span dir="ltr">{{ $vacancy->reference }}</span>@if ($vacancy->application_deadline) · {{ __('recruitment.career.apply_by') }} <span dir="ltr">{{ $vacancy->application_deadline->toDateString() }}</span>@endif</p>
            </header>
            @if ($vacancy->localized('description'))
                <section>
                    <h2 class="mb-2 text-h5">{{ __('recruitment.fields.description') }}</h2>
                    <p class="whitespace-pre-line text-body-md leading-relaxed">{{ $vacancy->localized('description') }}</p>
                </section>
            @endif
            @if ($vacancy->localized('requirements'))
                <section>
                    <h2 class="mb-2 text-h5">{{ __('recruitment.fields.requirements') }}</h2>
                    <p class="whitespace-pre-line text-body-md leading-relaxed">{{ $vacancy->localized('requirements') }}</p>
                </section>
            @endif
        </article>

        <aside class="lg:col-span-2">
            <x-card :title="__('recruitment.career.apply_title')" id="apply">
                @if (session('applied'))
                    <x-alert tone="success" :title="__('recruitment.career.thanks_title')" data-region="applied">{{ __('recruitment.career.thanks') }}</x-alert>
                @else
                    <form method="POST" action="{{ route('careers.apply', $vacancy->reference) }}" enctype="multipart/form-data" class="flex flex-col gap-4" data-validate>
                        @csrf
                        {{-- Honeypot: hidden from people, filled by bots. --}}
                        <div class="absolute -start-[10000px] size-px overflow-hidden" aria-hidden="true">
                            <label for="website">Website</label>
                            <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                        </div>
                        <x-form.input name="name" :label="__('recruitment.career.fields.name')" :value="old('name')" required minlength="3" maxlength="150" autocomplete="name" />
                        <x-form.input name="email" type="email" :label="__('recruitment.career.fields.email')" :value="old('email')" required maxlength="255" autocomplete="email" dir="ltr" />
                        <x-form.input name="phone" type="tel" :label="__('recruitment.career.fields.phone')" :value="old('phone')" required maxlength="30" autocomplete="tel" dir="ltr" />
                        <x-form.input name="city" :label="__('recruitment.career.fields.city')" :value="old('city')" maxlength="100" autocomplete="address-level2" />
                        <x-form.input name="current_job_title" :label="__('recruitment.career.fields.current_job_title')" :value="old('current_job_title')" maxlength="150" />
                        <x-form.input name="experience_years" type="number" step="0.5" min="0" max="60" :label="__('recruitment.career.fields.experience_years')" :value="old('experience_years')" />
                        <x-form.input name="linkedin_url" type="url" :label="__('recruitment.career.fields.linkedin_url')" :value="old('linkedin_url')" maxlength="255" dir="ltr" />
                        <x-form.file-input name="cv" :label="__('recruitment.career.fields.cv')" required accept="{{ collect($cvTypes)->map(fn ($ext) => '.'.$ext)->implode(',') }}"
                            :help="__('recruitment.candidates.cv_help', ['types' => implode(', ', $cvTypes), 'size' => $cvMaxKb])" />
                        <x-form.textarea name="cover_letter" :label="__('recruitment.career.fields.cover_letter')" rows="4" maxlength="5000">{{ old('cover_letter') }}</x-form.textarea>
                        <x-form.checkbox name="consent" :label="__('recruitment.career.fields.consent')" :checked="(bool) old('consent')" required />
                        @error('consent')<p class="text-caption-md text-feedback-danger-text">{{ $message }}</p>@enderror
                        <x-button icon="upload">{{ __('recruitment.career.submit') }}</x-button>
                    </form>
                @endif
            </x-card>
        </aside>
    </div>
@endsection
