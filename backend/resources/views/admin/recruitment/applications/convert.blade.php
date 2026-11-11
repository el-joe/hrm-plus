@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('recruitment.vacancies.title'), 'url' => route('admin.vacancies.index')],
        ['label' => $application->vacancy->title, 'url' => route('admin.vacancies.show', $application->vacancy)],
        ['label' => $application->candidate->name, 'url' => route('admin.applications.show', $application)],
    ],
])

@section('title', __('recruitment.convert.title'))

@section('content')
    <x-alert tone="info">{{ __('recruitment.convert.intro') }}</x-alert>
    @php($candidate = $application->candidate)
    <form method="POST" action="{{ route('admin.applications.convert.store', $application) }}" class="flex flex-col gap-4 lg:gap-6" data-validate data-employee-form
        data-confirm="{{ __('recruitment.convert.confirm', ['name' => $candidate->name]) }}" data-confirm-label="{{ __('recruitment.applications.hire') }}" data-confirm-tone="info">
        @csrf
        <x-form.section :title="__('recruitment.convert.section_person')" :description="__('recruitment.convert.section_person_help')">
            <x-form.input name="name_ar" :label="__('employees.fields.name_ar')" :value="old('name_ar', $candidate->name_ar)" required maxlength="150" dir="rtl" />
            <x-form.input name="name_en" :label="__('employees.fields.name_en')" :value="old('name_en', $candidate->name_en)" required maxlength="150" dir="ltr" />
            <x-form.input name="email" type="email" :label="__('employees.fields.email')" :value="old('email', $candidate->email)" maxlength="255" dir="ltr" />
            <x-form.input name="phone" type="tel" :label="__('employees.fields.phone')" :value="old('phone', $candidate->phone)" maxlength="30" dir="ltr" />
            <x-form.input name="date_of_birth" type="date" :label="__('employees.fields.date_of_birth')" :value="old('date_of_birth', $candidate->date_of_birth?->toDateString())" />
        </x-form.section>

        <x-form.section :title="__('recruitment.convert.section_job')" :description="__('recruitment.convert.section_job_help')">
            <x-form.input name="hire_date" type="date" :label="__('employees.fields.hire_date')" :value="old('hire_date', $offer?->start_date?->toDateString() ?? today()->toDateString())" required />
            <x-form.input name="probation_end_date" type="date" :label="__('employees.fields.probation_end_date')"
                :value="old('probation_end_date', $offer?->probation_months ? $offer->start_date->copy()->addMonths($offer->probation_months)->toDateString() : '')" />
            @include('admin.employees._placement-fields', ['options' => $options, 'values' => $values])
            @if ($canSetSalary)
                <x-form.input name="basic_salary" type="number" step="0.01" min="0" :label="__('employees.fields.basic_salary')" :value="old('basic_salary', $salary)" dir="ltr" :help="__('recruitment.convert.salary_help')" />
            @elseif ($salary !== null)
                <x-form.field :label="__('employees.fields.basic_salary')" :help="__('recruitment.convert.salary_from_offer')">
                    <p class="text-body-md tabular-nums" dir="ltr">{{ App\Support\Money::format($salary) }} {{ $offer->currency }}</p>
                </x-form.field>
            @endif
        </x-form.section>

        <x-form.section :title="__('recruitment.convert.section_account')" :description="__('recruitment.convert.section_account_help')">
            <div class="md:col-span-2">
                <input type="hidden" name="create_account" value="0">
                <x-form.switch name="create_account" :label="__('recruitment.convert.create_account')" :checked="(bool) old('create_account', true)" />
            </div>
            <x-form.input name="account_email" type="email" :label="__('recruitment.convert.account_email')" :value="old('account_email', $candidate->email)" maxlength="255" dir="ltr" />
            <x-form.select name="locale" :label="__('recruitment.fields.locale')">
                @foreach (['ar', 'en'] as $locale)
                    <option value="{{ $locale }}" @selected(old('locale', $candidate->locale ?? 'ar') === $locale)>{{ __('app.locales.'.$locale) }}</option>
                @endforeach
            </x-form.select>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.applications.show', $application)">{{ __('app.actions.cancel') }}</x-button>
            <x-button icon="user-plus">{{ __('recruitment.applications.hire') }}</x-button>
        </x-form.actions>
    </form>
@endsection
