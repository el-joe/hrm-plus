@extends('layouts.admin', [
    'breadcrumbs' => [
        ['label' => __('recruitment.vacancies.title'), 'url' => route('admin.vacancies.index')],
        ['label' => $application->vacancy->title, 'url' => route('admin.vacancies.show', $application->vacancy)],
    ],
    'subtitle' => $application->vacancy->reference.' · '.__('recruitment.fields.applied_at').' '.$application->applied_at->toDateString(),
])

@section('title', $application->candidate->name)

@section('page-actions')
    <x-button variant="secondary" :href="route('admin.candidates.show', $application->candidate)" icon="user">{{ __('recruitment.applications.candidate_profile') }}</x-button>
    @if ($application->isActive())
        @can('create', App\Models\Interview::class)
            <x-button variant="secondary" :href="route('admin.applications.interviews.create', $application)" icon="calendar">{{ __('recruitment.interviews.schedule') }}</x-button>
        @endcan
        @can('create', App\Models\JobOffer::class)
            @unless ($application->offers->contains(fn ($offer) => $offer->status->isOpen()))
                <x-button variant="secondary" :href="route('admin.applications.offers.create', $application)" icon="mail">{{ __('recruitment.offers.create') }}</x-button>
            @endunless
        @endcan
        @can('convert', $application)
            @if ($application->offers->first()?->status === App\Enums\OfferStatus::Accepted)
                <x-button :href="route('admin.applications.convert', $application)" icon="user-plus">{{ __('recruitment.applications.hire') }}</x-button>
            @endif
        @endcan
    @endif
@endsection

@section('content')
    @if ($application->employee)
        <x-alert tone="success" :title="__('recruitment.applications.hired_title', ['number' => $application->employee->employee_number, 'date' => $application->hired_at?->toDateString()])">
            <a class="text-text-link underline" href="{{ route('admin.employees.show', $application->employee) }}">{{ __('recruitment.candidates.open_employee') }}</a>
        </x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('recruitment.applications.status')">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-h5">{{ $application->stage->name }}</span>
                    @include('admin.recruitment._badge', ['value' => $application->status])
                    @if ($application->average_score !== null)
                        <x-badge tone="info">{{ __('recruitment.fields.average_score') }}: {{ $application->average_score }} / {{ config('recruitment.score_scale') }}</x-badge>
                    @endif
                </div>
                @if ($application->rejection_reason)
                    <p class="mt-2 text-body-sm text-text-secondary">{{ __('recruitment.fields.rejection_reason') }}: {{ $application->rejection_reason }}</p>
                @endif
                @can('update', $application)
                    @if ($application->isActive())
                        <form method="POST" action="{{ route('admin.applications.move', $application) }}" class="mt-4 grid gap-3 md:grid-cols-2" data-validate>
                            @csrf
                            @method('PATCH')
                            <x-form.select name="stage_id" :label="__('recruitment.pipeline.move_to_stage')" required>
                                @foreach ($stages as $stage)
                                    <option value="{{ $stage->id }}" @selected($stage->id === $application->stage_id) @disabled($stage->category === App\Enums\StageCategory::Hired)>{{ $stage->name }} ({{ $stage->category->label() }})</option>
                                @endforeach
                            </x-form.select>
                            <x-form.input name="rejection_reason" :label="__('recruitment.fields.rejection_reason')" maxlength="1000" :help="__('recruitment.applications.rejection_help')" />
                            <div class="md:col-span-2">
                                <x-form.textarea name="note" :label="__('recruitment.fields.note')" rows="2" maxlength="1000" />
                            </div>
                            <div><x-button variant="secondary" icon-end="arrow-right">{{ __('recruitment.pipeline.move') }}</x-button></div>
                        </form>
                    @endif
                @endcan
            </x-card>

            <x-card :title="__('recruitment.interviews.title')" padding="none">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($application->interviews as $interview)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.interviews.show', $interview) }}" class="block text-label-md text-text-primary hover:text-text-link">{{ $interview->type->label() }}</a>
                                <span class="text-caption-md text-text-tertiary"><span dir="ltr">{{ $interview->localStart()->format('Y-m-d H:i') }} ({{ $interview->timezone }})</span> · {{ $interview->interviewers->pluck('name')->implode(__('recruitment.common.separator')) }}</span>
                            </div>
                            @include('admin.recruitment._badge', ['value' => $interview->status])
                            @if ($interview->decision)
                                <x-badge>{{ $interview->decision->label() }}</x-badge>
                            @endif
                            <span class="text-body-sm tabular-nums text-text-secondary">{{ $interview->average_score ?? '—' }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-body-md text-text-tertiary">{{ __('recruitment.interviews.none') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('recruitment.offers.title')" padding="none">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($application->offers as $offer)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.offers.show', $offer) }}" class="block text-label-md text-text-primary hover:text-text-link" dir="ltr">{{ $offer->reference }}</a>
                                <span class="text-caption-md text-text-tertiary">{{ __('recruitment.fields.start_date') }} <span dir="ltr">{{ $offer->start_date->toDateString() }}</span>
                                    @if ($showSalary && $offer->basic_salary !== null) · <span dir="ltr" class="tabular-nums">{{ App\Support\Money::format($offer->basic_salary) }} {{ $offer->currency }}</span>@endif</span>
                            </div>
                            @include('admin.recruitment._badge', ['value' => $offer->status])
                        </li>
                    @empty
                        <li class="px-5 py-6 text-body-md text-text-tertiary">{{ __('recruitment.offers.none') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('recruitment.applications.history')">
                <ol class="flex flex-col gap-3 border-s border-border-default ps-4">
                    @foreach ($application->stageChanges as $change)
                        <li class="relative">
                            <span class="absolute -start-[1.3rem] top-1.5 size-2 rounded-full bg-action-primary-default" aria-hidden="true"></span>
                            <p class="text-label-md">{{ $change->from_stage_name ? $change->from_stage_name.' → ' : '' }}{{ $change->to_stage_name }}</p>
                            <p class="text-caption-md text-text-tertiary">{{ $change->changedBy?->name ?? __('recruitment.applications.system') }} · <span dir="ltr">{{ $change->created_at?->format('Y-m-d H:i') }}</span></p>
                            @if ($change->note)
                                <p class="text-body-sm text-text-secondary">{{ $change->note }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-card>

            @if ($application->cover_letter)
                <x-card :title="__('recruitment.fields.cover_letter')">
                    <p class="whitespace-pre-line text-body-sm">{{ $application->cover_letter }}</p>
                </x-card>
            @endif

            <x-card :title="__('recruitment.candidates.notes')">
                @can('update', $application)
                    <form method="POST" action="{{ route('admin.candidates.notes.store', $application->candidate) }}" class="mb-3 flex flex-col gap-2" data-validate>
                        @csrf
                        <input type="hidden" name="job_application_id" value="{{ $application->id }}">
                        <x-form.textarea name="body" :label="__('recruitment.candidates.new_note')" hide-label rows="2" required maxlength="5000" :placeholder="__('recruitment.candidates.note_placeholder')" />
                        <div><x-button size="sm" icon="plus">{{ __('recruitment.candidates.add_note') }}</x-button></div>
                    </form>
                @endcan
                <ul class="flex flex-col gap-2">
                    @forelse ($application->notes as $note)
                        <li class="rounded-md bg-surface-subtle p-3">
                            <p class="whitespace-pre-line text-body-sm">{{ $note->body }}</p>
                            <p class="mt-1 text-caption-md text-text-tertiary">{{ $note->author?->name }} · <span dir="ltr">{{ $note->created_at->format('Y-m-d H:i') }}</span></p>
                        </li>
                    @empty
                        <li class="text-body-sm text-text-tertiary">{{ __('recruitment.candidates.no_notes') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
@endsection
