@extends('layouts.employee')

@section('title', $interview->type->label().' — '.$interview->application->candidate->name)

@section('page-actions')
    <x-button variant="secondary" :href="route('portal.interviews.index')" icon="arrow-left">{{ __('app.actions.back') }}</x-button>
    @if ($interview->application->candidate->cv_path)
        <x-button variant="secondary" :href="route('portal.interviews.cv', $interview)" icon="download">{{ __('recruitment.candidates.download_cv') }}</x-button>
    @endif
@endsection

@section('content')
    <x-card :title="__('recruitment.interviews.details')">
        <x-detail-list>
            <x-detail-item :label="__('recruitment.fields.vacancy')">{{ $interview->application->vacancy->title }}</x-detail-item>
            <x-detail-item :label="__('recruitment.fields.scheduled_at')"><span dir="ltr">{{ \App\Support\Formatter::dateTime($interview->localStart(), $interview->timezone) }} – {{ \App\Support\Formatter::time($interview->localEnd(), $interview->timezone) }} ({{ $interview->timezone }})</span></x-detail-item>
            <x-detail-item :label="__('recruitment.fields.mode')">{{ $interview->mode->label() }}</x-detail-item>
            @if ($interview->location)
                <x-detail-item :label="__('recruitment.fields.location')">{{ $interview->location }}</x-detail-item>
            @endif
            @if ($interview->meeting_url)
                <x-detail-item :label="__('recruitment.fields.meeting_url')"><a href="{{ $interview->meeting_url }}" class="break-all text-text-link underline" target="_blank" rel="noopener noreferrer" dir="ltr">{{ $interview->meeting_url }}</a></x-detail-item>
            @endif
            <x-detail-item :label="__('recruitment.fields.interviewers')">{{ $interview->interviewers->pluck('name')->implode(__('recruitment.common.separator')) }}</x-detail-item>
            <x-detail-item :label="__('app.common.status')">@include('admin.recruitment._badge', ['value' => $interview->status])</x-detail-item>
        </x-detail-list>
    </x-card>

    <x-card :title="__('recruitment.scorecards.mine')" :subtitle="$myScorecard ? __('recruitment.scorecards.submitted_at', ['date' => \App\Support\Formatter::dateTime($myScorecard->submitted_at)]) : null">
        @if (in_array($interview->status, [App\Enums\InterviewStatus::Cancelled, App\Enums\InterviewStatus::NoShow], true))
            <x-alert tone="info">{{ __('recruitment.validation.interview_cancelled') }}</x-alert>
        @else
            @include('admin.recruitment.interviews._scorecard-form', ['action' => route('portal.interviews.scorecard', $interview)])
        @endif
    </x-card>
@endsection
