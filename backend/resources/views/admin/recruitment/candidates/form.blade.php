@extends('layouts.admin', [
    'breadcrumbs' => array_values(array_filter([
        ['label' => __('recruitment.candidates.title'), 'url' => route('admin.candidates.index')],
        $candidate->exists ? ['label' => $candidate->name, 'url' => route('admin.candidates.show', $candidate)] : null,
    ])),
])

@section('title', $candidate->exists ? __('recruitment.candidates.edit') : __('recruitment.candidates.create'))

@section('content')
    @php
        $value = function (string $field) use ($candidate): string {
            $current = $candidate->getAttribute($field);
            $current = $current instanceof \BackedEnum ? $current->value : ($current instanceof \Carbon\CarbonInterface ? $current->toDateString() : $current);

            return (string) old($field, is_array($current) ? implode(', ', $current) : $current);
        };
    @endphp

    @if (session('duplicateCandidates'))
        <x-alert tone="warning" :title="__('recruitment.candidates.duplicates_title')" data-region="duplicates">
            <ul class="mt-1 list-disc ps-5">
                @foreach (session('duplicateCandidates') as $duplicate)
                    <li>
                        <a class="text-text-link underline" href="{{ route('admin.candidates.show', $duplicate['id']) }}">{{ $duplicate['name_en'] ?: $duplicate['name_ar'] }}</a>
                        <span dir="ltr">{{ $duplicate['email'] }} {{ $duplicate['phone'] }}</span>
                    </li>
                @endforeach
            </ul>
            <p class="mt-2">{{ __('recruitment.candidates.duplicates_help') }}</p>
        </x-alert>
    @endif

    <form method="POST" action="{{ $candidate->exists ? route('admin.candidates.update', $candidate) : route('admin.candidates.store') }}"
        class="flex flex-col gap-4 lg:gap-6" data-validate enctype="multipart/form-data">
        @csrf
        @if ($candidate->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('recruitment.candidates.section_person')" :description="__('recruitment.candidates.section_person_help')">
            <x-form.input name="name_ar" :label="__('recruitment.fields.name_ar')" :value="$value('name_ar')" maxlength="150" dir="rtl" />
            <x-form.input name="name_en" :label="__('recruitment.fields.name_en')" :value="$value('name_en')" maxlength="150" dir="ltr" />
            <x-form.input name="email" type="email" :label="__('app.common.email')" :value="$value('email')" maxlength="255" dir="ltr" />
            <x-form.input name="phone" type="tel" :label="__('recruitment.fields.phone')" :value="$value('phone')" maxlength="30" dir="ltr" />
            <x-form.input name="city" :label="__('recruitment.fields.city')" :value="$value('city')" maxlength="100" />
            <x-form.input name="nationality" :label="__('recruitment.fields.nationality')" :value="$value('nationality')" maxlength="2" dir="ltr" :help="__('recruitment.candidates.country_help')" />
            <x-form.input name="date_of_birth" type="date" :label="__('recruitment.fields.date_of_birth')" :value="$value('date_of_birth')" />
            <x-form.select name="gender" :label="__('recruitment.fields.gender')">
                <option value="">—</option>
                @foreach (App\Enums\Gender::cases() as $gender)
                    <option value="{{ $gender->value }}" @selected($value('gender') === $gender->value)>{{ $gender->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="locale" :label="__('recruitment.fields.locale')" :help="__('recruitment.candidates.locale_help')">
                @foreach (['ar', 'en'] as $locale)
                    <option value="{{ $locale }}" @selected(old('locale', $candidate->locale ?? app()->getLocale()) === $locale)>{{ __('app.locales.'.$locale) }}</option>
                @endforeach
            </x-form.select>
        </x-form.section>

        <x-form.section :title="__('recruitment.candidates.section_profile')" :description="__('recruitment.candidates.section_profile_help')">
            <x-form.input name="current_job_title" :label="__('recruitment.fields.current_job_title')" :value="$value('current_job_title')" maxlength="150" />
            <x-form.input name="current_employer" :label="__('recruitment.fields.current_employer')" :value="$value('current_employer')" maxlength="150" />
            <x-form.input name="experience_years" type="number" step="0.5" min="0" max="60" :label="__('recruitment.fields.experience_years')" :value="$value('experience_years')" />
            <x-form.input name="linkedin_url" type="url" :label="__('recruitment.fields.linkedin_url')" :value="$value('linkedin_url')" dir="ltr" />
            <x-form.input name="portfolio_url" type="url" :label="__('recruitment.fields.portfolio_url')" :value="$value('portfolio_url')" dir="ltr" />
            <x-form.input name="skills" :label="__('recruitment.fields.skills')" :value="$value('skills')" maxlength="2000" :help="__('recruitment.candidates.list_help')" />
            <div class="md:col-span-2">
                <x-form.textarea name="qualifications" :label="__('recruitment.fields.qualifications')" rows="3" maxlength="5000">{{ $value('qualifications') }}</x-form.textarea>
            </div>
            <x-form.input name="tags" :label="__('recruitment.fields.tags')" :value="old('tags', $candidate->exists ? $candidate->tags->pluck('name')->implode(', ') : '')" maxlength="1000" :help="__('recruitment.candidates.list_help')" />
            <x-form.file-input name="cv" :label="__('recruitment.fields.cv')" accept="{{ collect(setting('recruitment.cv_allowed_extensions'))->map(fn ($ext) => '.'.$ext)->implode(',') }}"
                :help="__('recruitment.candidates.cv_help', ['types' => implode(', ', (array) setting('recruitment.cv_allowed_extensions')), 'size' => (int) setting('recruitment.cv_max_size_kb')]).($candidate->cv_name ? ' '.__('recruitment.candidates.cv_current', ['name' => $candidate->cv_name]) : '')" />
        </x-form.section>

        <x-form.section :title="__('recruitment.candidates.section_source')" :description="__('recruitment.candidates.section_source_help')">
            <x-form.select name="source" :label="__('recruitment.fields.source')" required>
                @foreach ($sources as $source)
                    <option value="{{ $source->value }}" @selected($value('source') === $source->value)>{{ $source->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="source_detail" :label="__('recruitment.fields.source_detail')" :value="$value('source_detail')" maxlength="150" />
            @unless ($candidate->exists)
                <x-form.select name="vacancy_id" :label="__('recruitment.fields.vacancy')" :required="auth()->user()->hasDataRestrictions()" :help="__('recruitment.candidates.vacancy_help')">
                    <option value="">{{ __('recruitment.candidates.no_vacancy') }}</option>
                    @foreach ($vacancies as $vacancy)
                        <option value="{{ $vacancy->id }}" @selected((string) old('vacancy_id', $selectedVacancy) === (string) $vacancy->id)>{{ $vacancy->reference }} · {{ $vacancy->title }}</option>
                    @endforeach
                </x-form.select>
            @endunless
            @if ($showSalary)
                <x-form.input name="expected_salary" type="number" step="0.01" min="0" :label="__('recruitment.fields.expected_salary')" :value="$value('expected_salary')" dir="ltr" :help="__('recruitment.common.restricted_help')" />
                <x-form.input name="current_salary" type="number" step="0.01" min="0" :label="__('recruitment.fields.current_salary')" :value="$value('current_salary')" dir="ltr" :help="__('recruitment.common.restricted_help')" />
            @endif
            @if (session('duplicateCandidates'))
                <div class="md:col-span-2">
                    <x-form.checkbox name="confirm_duplicate" :label="__('recruitment.candidates.confirm_duplicate')" />
                </div>
            @endif
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="$candidate->exists ? route('admin.candidates.show', $candidate) : route('admin.candidates.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
