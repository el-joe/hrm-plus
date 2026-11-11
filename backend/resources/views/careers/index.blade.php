@extends('layouts.public')

@section('title', __('recruitment.career.title'))

@section('content')
    <section class="rounded-2xl bg-surface-brand px-6 py-10 text-text-on-primary md:px-10">
        <h1 class="text-h2 md:text-h1">{{ __('recruitment.career.heading', ['company' => $company?->name ?? config('app.name')]) }}</h1>
        <p class="mt-2 max-w-2xl text-body-lg text-text-on-brand-muted">{{ __('recruitment.career.intro') }}</p>
    </section>

    <h2 class="text-h4">{{ trans_choice('recruitment.career.open_positions', $vacancies->count(), ['count' => $vacancies->count()]) }}</h2>

    @forelse ($vacancies as $vacancy)
        <a href="{{ route('careers.show', $vacancy->reference) }}" class="group block rounded-xl border border-border-subtle bg-surface-default p-5 shadow-xs transition hover:border-border-strong hover:shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="min-w-0 flex-1">
                    <p class="text-h5 group-hover:text-text-link">{{ $vacancy->title }}</p>
                    <p class="text-body-sm text-text-secondary">{{ $vacancy->department?->name }} · {{ $vacancy->branch?->name }}@if ($vacancy->employmentType) · {{ $vacancy->employmentType->name }}@endif</p>
                </div>
                @if ($vacancy->application_deadline)
                    <span class="text-caption-md text-text-tertiary">{{ __('recruitment.career.apply_by') }} <span dir="ltr">{{ $vacancy->application_deadline->toDateString() }}</span></span>
                @endif
                <x-icon name="chevron-right" flip class="hidden text-text-tertiary sm:block" />
            </div>
        </a>
    @empty
        <x-card><x-empty-state icon="briefcase" :title="__('recruitment.career.none')" :description="__('recruitment.career.none_help')" /></x-card>
    @endforelse
@endsection
