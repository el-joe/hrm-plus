{{-- Printable offer letter (HTML; the browser's "Print / Save as PDF" produces the PDF). --}}
@php
    $candidate = $offer->application->candidate;
    $vacancy = $offer->application->vacancy;
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('app.supported_locales.'.app()->getLocale(), 'rtl') }}">
<head>
    @include('partials.head')
</head>
<body class="bg-surface-page text-text-primary print:bg-neutral-0">
    <div class="mx-auto flex max-w-200 items-center justify-between gap-3 px-4 py-4 print:hidden">
        <x-button variant="secondary" :href="route('admin.offers.show', $offer)" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
        <x-button type="button" icon="download" data-print hidden data-js-only>{{ __('recruitment.offers.print') }}</x-button>
    </div>
    <main class="mx-auto mb-10 max-w-200 rounded-xl bg-surface-default p-8 shadow-sm sm:p-12 print:m-0 print:max-w-none print:rounded-none print:p-0 print:shadow-none">
        <header class="mb-10 flex items-start justify-between gap-6 border-b border-border-subtle pb-6">
            <div>
                <p class="text-h3">{{ $company?->name ?? config('app.name') }}</p>
                @if ($company?->localized('address'))
                    <p class="text-body-sm text-text-secondary">{{ $company->localized('address') }}</p>
                @endif
            </div>
            <div class="text-end text-body-sm text-text-secondary">
                <p dir="ltr">{{ $offer->reference }}</p>
                <p dir="ltr">{{ ($offer->sent_at ?? now())->toDateString() }}</p>
            </div>
        </header>

        <h1 class="mb-6 text-h2">{{ __('recruitment.letter.title') }}</h1>
        <p class="mb-4 text-body-lg">{{ __('recruitment.letter.dear', ['name' => $candidate->name]) }}</p>
        <p class="mb-6 text-body-md leading-relaxed">{{ __('recruitment.letter.intro', ['company' => $company?->name ?? config('app.name'), 'title' => $offer->jobTitle?->name ?? $vacancy->title]) }}</p>

        <dl class="mb-6 grid gap-x-6 gap-y-3 rounded-lg border border-border-subtle p-5 sm:grid-cols-2">
            <div><dt class="text-label-sm text-text-tertiary">{{ __('recruitment.fields.job_title_id') }}</dt><dd>{{ $offer->jobTitle?->name ?? $vacancy->title }}</dd></div>
            <div><dt class="text-label-sm text-text-tertiary">{{ __('recruitment.fields.department_id') }}</dt><dd>{{ $vacancy->department?->name }} · {{ $vacancy->branch?->name }}</dd></div>
            <div><dt class="text-label-sm text-text-tertiary">{{ __('recruitment.fields.employment_type_id') }}</dt><dd>{{ $offer->employmentType?->name ?? '—' }}</dd></div>
            <div><dt class="text-label-sm text-text-tertiary">{{ __('recruitment.fields.start_date') }}</dt><dd dir="ltr" class="text-start">{{ $offer->start_date->toDateString() }}</dd></div>
            @if ($showSalary)
                <div><dt class="text-label-sm text-text-tertiary">{{ __('recruitment.letter.salary') }}</dt><dd dir="ltr" class="text-start tabular-nums">{{ App\Support\Money::format($offer->basic_salary) }} {{ $offer->currency }}</dd></div>
            @endif
            @if ($offer->probation_months)
                <div><dt class="text-label-sm text-text-tertiary">{{ __('recruitment.fields.probation_months') }}</dt><dd>{{ $offer->probation_months }}</dd></div>
            @endif
        </dl>

        @if ($offer->benefits)
            <h2 class="mb-2 text-h5">{{ __('recruitment.fields.benefits') }}</h2>
            <p class="mb-6 whitespace-pre-line text-body-md">{{ $offer->benefits }}</p>
        @endif
        @if ($offer->terms)
            <h2 class="mb-2 text-h5">{{ __('recruitment.fields.terms') }}</h2>
            <p class="mb-6 whitespace-pre-line text-body-md">{{ $offer->terms }}</p>
        @endif

        <p class="mb-10 text-body-md">{{ __('recruitment.letter.expiry', ['date' => $offer->expires_on->toDateString()]) }}</p>

        <div class="grid gap-10 sm:grid-cols-2">
            <div class="border-t border-border-strong pt-2 text-body-sm">{{ __('recruitment.letter.for_company', ['company' => $company?->name ?? config('app.name')]) }}</div>
            <div class="border-t border-border-strong pt-2 text-body-sm">{{ __('recruitment.letter.accepted_by', ['name' => $candidate->name]) }}</div>
        </div>
    </main>
</body>
</html>
