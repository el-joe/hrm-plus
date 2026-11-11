@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('recruitment.vacancies.title'), 'url' => route('admin.vacancies.index')],
        ['label' => $application->vacancy->title, 'url' => route('admin.vacancies.show', $application->vacancy)],
        ['label' => $application->candidate->name, 'url' => route('admin.applications.show', $application)],
    ],
])

@section('title', $offer->exists ? __('recruitment.offers.edit') : __('recruitment.offers.create'))

@section('content')
    <form method="POST" action="{{ $offer->exists ? route('admin.offers.update', $offer) : route('admin.applications.offers.store', $application) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($offer->exists)
            @method('PUT')
        @endif
        <x-form.section :title="__('recruitment.offers.section_job')" :description="__('recruitment.offers.section_job_help')">
            <x-form.select name="position_id" :label="__('recruitment.fields.position_id')">
                <option value="">{{ __('recruitment.common.none') }}</option>
                @foreach ($options['positions'] as $position)
                    <option value="{{ $position->id }}" @selected((string) old('position_id', $offer->position_id) === (string) $position->id)>{{ $position->code }} · {{ $position->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="job_title_id" :label="__('recruitment.fields.job_title_id')">
                <option value="">{{ __('recruitment.common.none') }}</option>
                @foreach ($options['jobTitles'] as $jobTitle)
                    <option value="{{ $jobTitle->id }}" @selected((string) old('job_title_id', $offer->job_title_id) === (string) $jobTitle->id)>{{ $jobTitle->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="employment_type_id" :label="__('recruitment.fields.employment_type_id')">
                <option value="">{{ __('recruitment.common.none') }}</option>
                @foreach ($options['employmentTypes'] as $type)
                    <option value="{{ $type->id }}" @selected((string) old('employment_type_id', $offer->employment_type_id) === (string) $type->id)>{{ $type->name }}</option>
                @endforeach
            </x-form.select>
            @if ($showSalary)
                <x-form.input name="basic_salary" type="number" step="0.01" min="0" :label="__('recruitment.fields.basic_salary').' ('.setting('localisation.currency_code').')'" :value="old('basic_salary', $offer->basic_salary)" dir="ltr" :help="__('recruitment.common.restricted_help')" />
            @else
                <x-alert tone="info" class="md:col-span-1">{{ __('recruitment.offers.salary_hidden') }}</x-alert>
            @endif
            <x-form.input name="start_date" type="date" :label="__('recruitment.fields.start_date')" :value="old('start_date', $offer->start_date?->toDateString())" required />
            <x-form.input name="expires_on" type="date" :label="__('recruitment.fields.expires_on')" :value="old('expires_on', $offer->expires_on?->toDateString())" required />
            <x-form.input name="probation_months" type="number" min="0" max="12" :label="__('recruitment.fields.probation_months')" :value="old('probation_months', $offer->probation_months)" />
        </x-form.section>
        <x-form.section :title="__('recruitment.offers.section_terms')" :description="__('recruitment.offers.section_terms_help')" :columns="1">
            <x-form.textarea name="benefits" :label="__('recruitment.fields.benefits')" rows="4" maxlength="5000">{{ old('benefits', $offer->benefits) }}</x-form.textarea>
            <x-form.textarea name="terms" :label="__('recruitment.fields.terms')" rows="6" maxlength="10000">{{ old('terms', $offer->terms) }}</x-form.textarea>
        </x-form.section>
        <x-form.actions>
            <x-button variant="secondary" :href="$offer->exists ? route('admin.offers.show', $offer) : route('admin.applications.show', $application)">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
