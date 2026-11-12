@extends('layouts.employee')

@section('title', $enrolment->session->course->name)

@section('content')
    <div>
        <x-button variant="ghost" size="sm" icon="arrow-left" :href="route('portal.training')">{{ __('training.portal.back') }}</x-button>
    </div>
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <x-card :title="__('training.sessions.details')" class="lg:col-span-1">
            <x-slot:actions><x-badge :tone="$enrolment->status->tone()" dot>{{ $enrolment->status->label() }}</x-badge></x-slot:actions>
            <x-detail-list>
                <x-detail-item :label="__('training.fields.dates')">@include('admin.training._session-when', ['session' => $enrolment->session])</x-detail-item>
                <x-detail-item :label="__('training.fields.delivery_mode')">{{ $enrolment->session->delivery_mode->label() }}</x-detail-item>
                @if ($enrolment->session->location)
                    <x-detail-item :label="__('training.fields.location')">{{ $enrolment->session->location }}</x-detail-item>
                @endif
                @if ($enrolment->session->meeting_url && $enrolment->isEnrolled())
                    <x-detail-item :label="__('training.fields.meeting_url')"><a class="break-all text-text-link hover:underline" href="{{ $enrolment->session->meeting_url }}" target="_blank" rel="noopener noreferrer" dir="ltr">{{ $enrolment->session->meeting_url }}</a></x-detail-item>
                @endif
                <x-detail-item :label="__('training.fields.trainer')">{{ $enrolment->session->trainer ?? '—' }}</x-detail-item>
                <x-detail-item :label="__('training.fields.provider')">{{ $enrolment->session->course->provider?->name ?? '—' }}</x-detail-item>
                <x-detail-item :label="__('training.fields.attendance_rate')"><span dir="ltr">{{ $enrolment->attendanceRate() }}%</span></x-detail-item>
                @if ($enrolment->score !== null)
                    <x-detail-item :label="__('training.fields.score')">{{ $enrolment->score }}</x-detail-item>
                @endif
                @if ($enrolment->rejection_reason)
                    <x-detail-item :label="__('training.common.reject_reason')">{{ $enrolment->rejection_reason }}</x-detail-item>
                @endif
            </x-detail-list>
        </x-card>

        <x-card :title="__('training.sessions.attendance')" padding="none" class="lg:col-span-2">
            <ul class="divide-y divide-border-subtle">
                @foreach ($days as $date)
                    @php($row = $enrolment->attendances->first(fn ($attendance) => $attendance->date->toDateString() === $date))
                    <li class="flex items-center justify-between gap-2 px-5 py-3">
                        <span dir="ltr" class="tabular-nums text-body-sm">{{ $date }}</span>
                        @if ($row)
                            <x-badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-badge>
                        @else
                            <span class="text-caption-md text-text-tertiary">{{ __('training.sessions.not_recorded') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    </div>
@endsection
