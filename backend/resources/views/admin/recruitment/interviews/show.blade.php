@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('recruitment.interviews.title'), 'url' => route('admin.interviews.index')]],
    'subtitle' => $interview->application->vacancy->title,
])

@section('title', $interview->type->label().' — '.$interview->application->candidate->name)

@section('page-actions')
    @if ($interview->application->candidate->cv_path)
        <x-button variant="secondary" :href="route('admin.interviews.cv', $interview)" icon="download">{{ __('recruitment.candidates.download_cv') }}</x-button>
    @endif
    @if ($canOpenApplication)
        <x-button variant="secondary" :href="route('admin.applications.show', $interview->application)" icon="briefcase">{{ __('recruitment.interviews.open_application') }}</x-button>
    @endif
    @if ($canUpdate && $interview->status === App\Enums\InterviewStatus::Scheduled)
        <x-button variant="secondary" :href="route('admin.interviews.edit', $interview)" icon="pencil">{{ __('recruitment.interviews.reschedule') }}</x-button>
        <x-dropdown>
            <x-slot:trigger>
                <span class="inline-flex h-10 items-center gap-2 rounded-md border border-border-default bg-surface-default px-3 text-label-md text-text-primary shadow-xs hover:bg-surface-subtle">
                    {{ __('recruitment.interviews.mark_as') }} <x-icon name="chevron-down" size="sm" />
                </span>
            </x-slot:trigger>
            <x-dropdown.item :action="route('admin.interviews.status', [$interview, 'status' => 'completed'])" method="PATCH" icon="check-circle">{{ App\Enums\InterviewStatus::Completed->label() }}</x-dropdown.item>
            <x-dropdown.item :action="route('admin.interviews.status', [$interview, 'status' => 'no_show'])" method="PATCH" icon="x-circle">{{ App\Enums\InterviewStatus::NoShow->label() }}</x-dropdown.item>
            <x-dropdown.divider />
            <x-dropdown.item :action="route('admin.interviews.status', [$interview, 'status' => 'cancelled'])" method="PATCH" icon="x" tone="danger"
                data-confirm="{{ __('recruitment.interviews.cancel_confirm') }}">{{ __('recruitment.interviews.cancel') }}</x-dropdown.item>
        </x-dropdown>
    @endif
@endsection

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('recruitment.interviews.details')">
                <div class="mb-4 flex flex-wrap gap-2">
                    @include('admin.recruitment._badge', ['value' => $interview->status])
                    @if ($interview->decision)
                        <x-badge tone="primary">{{ __('recruitment.fields.decision') }}: {{ $interview->decision->label() }}</x-badge>
                    @endif
                    @if ($interview->average_score !== null)
                        <x-badge tone="info">{{ __('recruitment.fields.average_score') }}: {{ $interview->average_score }} / {{ config('recruitment.score_scale') }}</x-badge>
                    @endif
                </div>
                <x-detail-list>
                    <x-detail-item :label="__('recruitment.fields.scheduled_at')">
                        <span dir="ltr">{{ $interview->localStart()->format('Y-m-d H:i') }} – {{ $interview->localEnd()->format('H:i') }}</span>
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $interview->timezone }}</span>
                    </x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.mode')">{{ $interview->mode->label() }}</x-detail-item>
                    @if ($interview->location)
                        <x-detail-item :label="__('recruitment.fields.location')">{{ $interview->location }}</x-detail-item>
                    @endif
                    @if ($interview->meeting_url)
                        <x-detail-item :label="__('recruitment.fields.meeting_url')"><a href="{{ $interview->meeting_url }}" class="break-all text-text-link underline" target="_blank" rel="noopener noreferrer" dir="ltr">{{ $interview->meeting_url }}</a></x-detail-item>
                    @endif
                    <x-detail-item :label="__('recruitment.fields.interviewers')">{{ $interview->interviewers->pluck('name')->implode(__('recruitment.common.separator')) }}</x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.evaluation_form_id')">{{ $interview->evaluationForm?->name ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('recruitment.interviews.organiser')">{{ $interview->organiser?->name ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.stage')">{{ $interview->application->stage->name }}</x-detail-item>
                </x-detail-list>
                @if ($interview->notes)
                    <p class="mt-4 whitespace-pre-line text-body-sm text-text-secondary">{{ $interview->notes }}</p>
                @endif
            </x-card>

            @if ($canScore)
                <x-card :title="__('recruitment.scorecards.mine')" :subtitle="$myScorecard ? __('recruitment.scorecards.submitted_at', ['date' => $myScorecard->submitted_at->format('Y-m-d H:i')]) : null">
                    @include('admin.recruitment.interviews._scorecard-form', ['action' => route('admin.interviews.scorecard', $interview)])
                </x-card>
            @endif

            @if ($canSeeScorecards)
                <x-card :title="__('recruitment.scorecards.all')" padding="none">
                    @forelse ($interview->scorecards as $scorecard)
                        <details class="border-b border-border-subtle px-5 py-3 last:border-0">
                            <summary class="flex cursor-pointer flex-wrap items-center gap-2">
                                <span class="text-label-md">{{ $scorecard->interviewer->name }}</span>
                                <x-badge :tone="in_array($scorecard->recommendation->value, ['strong_yes', 'yes'], true) ? 'success' : 'danger'">{{ $scorecard->recommendation->label() }}</x-badge>
                                <span class="ms-auto text-label-md tabular-nums">{{ $scorecard->overall_score ?? '—' }}</span>
                            </summary>
                            <ul class="mt-2 flex flex-col gap-1 text-body-sm">
                                @foreach ($interview->evaluationForm?->criteria ?? [] as $criterion)
                                    @php($score = $scorecard->scores->firstWhere('evaluation_criterion_id', $criterion->id))
                                    <li class="flex justify-between gap-3"><span>{{ $criterion->name }}</span><span class="tabular-nums">{{ $score?->score ?? '—' }}</span></li>
                                @endforeach
                            </ul>
                            @if ($scorecard->comments)
                                <p class="mt-2 whitespace-pre-line text-body-sm text-text-secondary">{{ $scorecard->comments }}</p>
                            @endif
                        </details>
                    @empty
                        <p class="px-5 py-6 text-body-md text-text-tertiary">{{ __('recruitment.scorecards.none') }}</p>
                    @endforelse
                </x-card>
            @endif
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('recruitment.fields.candidate')">
                <p class="text-h5">{{ $interview->application->candidate->name }}</p>
                <p class="text-body-sm text-text-secondary">{{ $interview->application->candidate->current_job_title }}</p>
                <p class="mt-2 text-caption-md text-text-tertiary" dir="ltr">{{ $interview->application->candidate->email }}</p>
            </x-card>

            @if ($canUpdate && $interview->isOpen())
                <x-card :title="__('recruitment.fields.decision')">
                    <form method="POST" action="{{ route('admin.interviews.decision', $interview) }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.select name="decision" :label="__('recruitment.fields.decision')" required>
                            <option value="">—</option>
                            @foreach ($decisions as $decision)
                                <option value="{{ $decision->value }}" @selected(old('decision', $interview->decision?->value) === $decision->value)>{{ $decision->label() }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.textarea name="decision_note" :label="__('recruitment.fields.note')" rows="3" maxlength="1000">{{ old('decision_note', $interview->decision_note) }}</x-form.textarea>
                        <div><x-button variant="secondary">{{ __('recruitment.interviews.save_decision') }}</x-button></div>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
@endsection
