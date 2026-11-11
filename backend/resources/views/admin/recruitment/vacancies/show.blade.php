@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('recruitment.vacancies.title'), 'url' => route('admin.vacancies.index')]],
    'subtitle' => $vacancy->reference.' · '.$vacancy->department?->name.' · '.$vacancy->branch?->name,
])

@section('title', $vacancy->title)

@section('page-actions')
    @can('create', App\Models\Candidate::class)
        @if ($vacancy->acceptsApplications())
            <x-button :href="route('admin.candidates.create', ['vacancy' => $vacancy->id])" icon="user-plus">{{ __('recruitment.candidates.create') }}</x-button>
        @endif
    @endcan
    @can('update', $vacancy)
        <x-button variant="secondary" :href="route('admin.vacancies.edit', $vacancy)" icon="pencil">{{ __('app.actions.edit') }}</x-button>
    @endcan
    @if (auth()->user()->can('managePipeline', $vacancy) || auth()->user()->can('delete', $vacancy))
        <x-dropdown>
            <x-slot:trigger>
                <span class="inline-flex h-10 items-center gap-2 rounded-md border border-border-default bg-surface-default px-3 text-label-md text-text-primary shadow-xs hover:bg-surface-subtle">
                    <x-icon name="more" size="sm" /><span class="sr-only">{{ __('app.common.actions') }}</span>
                </span>
            </x-slot:trigger>
            @can('managePipeline', $vacancy)
                @if ($vacancy->uses_custom_pipeline)
                    <x-dropdown.item :href="route('admin.recruitment-stages.index', ['vacancy' => $vacancy->id])" icon="layers">{{ __('recruitment.vacancies.edit_pipeline') }}</x-dropdown.item>
                    <x-dropdown.item :action="route('admin.vacancies.pipeline.reset', $vacancy)" method="DELETE" icon="history"
                        data-confirm="{{ __('recruitment.vacancies.reset_pipeline_confirm') }}" data-confirm-tone="warning">{{ __('recruitment.vacancies.reset_pipeline') }}</x-dropdown.item>
                @else
                    <x-dropdown.item :action="route('admin.vacancies.pipeline.customise', $vacancy)" icon="layers">{{ __('recruitment.vacancies.customise_pipeline') }}</x-dropdown.item>
                @endif
            @endcan
            @can('delete', $vacancy)
                <x-dropdown.divider />
                <x-dropdown.item :action="route('admin.vacancies.destroy', $vacancy)" method="DELETE" icon="trash" tone="danger"
                    data-confirm="{{ __('recruitment.vacancies.delete_confirm', ['reference' => $vacancy->reference]) }}">{{ __('app.actions.delete') }}</x-dropdown.item>
            @endcan
        </x-dropdown>
    @endif
@endsection

@section('content')
    <x-card data-region="summary">
        <div class="flex flex-wrap items-center gap-2">
            @include('admin.recruitment._badge', ['value' => $vacancy->status])
            @if ($vacancy->isPublished())
                <x-badge tone="success">{{ __('recruitment.vacancies.on_career_page') }}</x-badge>
            @elseif ($vacancy->is_public)
                <x-badge tone="neutral">{{ __('recruitment.vacancies.public_not_live') }}</x-badge>
            @endif
            @if ($vacancy->uses_custom_pipeline)
                <x-badge tone="info">{{ __('recruitment.vacancies.custom_pipeline') }}</x-badge>
            @endif
        </div>
        <x-detail-list class="mt-4">
            <x-detail-item :label="__('recruitment.fields.openings')"><span class="tabular-nums">{{ $vacancy->filled_count }} / {{ $vacancy->openings }}</span></x-detail-item>
            <x-detail-item :label="__('recruitment.fields.position_id')">{{ $vacancy->position?->name ?? '—' }}</x-detail-item>
            <x-detail-item :label="__('recruitment.fields.job_title_id')">{{ $vacancy->jobTitle?->name ?? '—' }}</x-detail-item>
            <x-detail-item :label="__('recruitment.fields.employment_type_id')">{{ $vacancy->employmentType?->name ?? '—' }}</x-detail-item>
            <x-detail-item :label="__('recruitment.fields.publish_at')"><span dir="ltr">{{ $vacancy->publish_at?->toDateString() ?? '—' }}</span></x-detail-item>
            <x-detail-item :label="__('recruitment.fields.application_deadline')"><span dir="ltr">{{ $vacancy->application_deadline?->toDateString() ?? '—' }}</span></x-detail-item>
            <x-detail-item :label="__('recruitment.fields.hiring_manager_id')">{{ $vacancy->hiringManager?->name ?? '—' }}</x-detail-item>
            <x-detail-item :label="__('recruitment.fields.evaluation_form_id')">{{ $vacancy->evaluationForm?->name ?? '—' }}</x-detail-item>
            @if ($showSalary)
                <x-detail-item :label="__('recruitment.fields.salary_range')">
                    <span dir="ltr" class="tabular-nums">{{ App\Support\Money::format($vacancy->salary_min) }} – {{ App\Support\Money::format($vacancy->salary_max) }} {{ $vacancy->currency }}</span>
                </x-detail-item>
            @endif
        </x-detail-list>
        @if ($vacancy->localized('description') || $vacancy->localized('requirements'))
            <details class="mt-4 rounded-md border border-border-subtle p-3">
                <summary class="cursor-pointer text-label-md text-text-link">{{ __('recruitment.vacancies.description_and_requirements') }}</summary>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div>
                        <p class="mb-1 text-label-sm text-text-tertiary">{{ __('recruitment.fields.description') }}</p>
                        <p class="whitespace-pre-line text-body-md">{{ $vacancy->localized('description') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="mb-1 text-label-sm text-text-tertiary">{{ __('recruitment.fields.requirements') }}</p>
                        <p class="whitespace-pre-line text-body-md">{{ $vacancy->localized('requirements') ?? '—' }}</p>
                    </div>
                </div>
            </details>
        @endif
    </x-card>

    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <x-tabs :label="__('recruitment.pipeline.views')" :items="[
            ['label' => __('recruitment.pipeline.board'), 'url' => route('admin.vacancies.show', [$vacancy, 'search' => $filters['search'] ?? null]), 'active' => $view === 'board'],
            ['label' => __('recruitment.pipeline.list'), 'url' => route('admin.vacancies.show', [$vacancy, 'view' => 'list', 'search' => $filters['search'] ?? null]), 'active' => $view === 'list'],
        ]" />
        <form method="GET" action="{{ route('admin.vacancies.show', $vacancy) }}" role="search" class="flex gap-2">
            @if ($view === 'list')
                <input type="hidden" name="view" value="list">
            @endif
            <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('recruitment.pipeline.search')" :value="$filters['search'] ?? ''" class="md:w-64" />
            <x-button variant="secondary" icon="filter">{{ __('app.actions.filter') }}</x-button>
        </form>
    </div>

    @if ($stages->isEmpty())
        <x-alert tone="warning">{{ __('recruitment.pipeline.no_stages') }}</x-alert>
    @elseif ($view === 'list')
        @include('admin.recruitment.vacancies._list')
    @else
        @include('admin.recruitment.vacancies._board')
    @endif
@endsection
