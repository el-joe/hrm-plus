@php use App\Services\Attendance\AttendanceClock; use App\Services\Attendance\Mobile\AttemptReason; $tz = $attempt->timezone; @endphp
@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('mobile.review.title'), 'url' => route('admin.attendance.reviews.index')]],
    'subtitle' => $attempt->employee->employee_number.' · '.$attempt->server_time->setTimezone($tz)->translatedFormat('l j F Y H:i'),
])

@section('title', $attempt->employee->name)

@section('content')
    <div class="flex flex-wrap items-center gap-2" data-region="attempt-outcome">
        @include('admin.attendance.reviews._outcome')
        <x-badge>{{ $attempt->direction->label() }}</x-badge>
        @foreach ($attempt->reasonCodes() as $code)
            <x-badge tone="warning">{{ AttemptReason::label($code) }}</x-badge>
        @endforeach
    </div>

    @if ($attempt->isPendingReview())
        <x-alert tone="warning" :title="__('mobile.review.pending_title')">{{ __('mobile.review.pending_help') }}</x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
        <x-card :title="__('mobile.review.location')">
            <x-detail-list>
                <x-detail-item :label="__('mobile.fields.coordinates')"><span dir="ltr">{{ $attempt->latitude }}, {{ $attempt->longitude }}</span></x-detail-item>
                <x-detail-item :label="__('mobile.fields.accuracy')"><span dir="ltr">{{ (float) $attempt->accuracy_meters }} m</span></x-detail-item>
                <x-detail-item :label="__('mobile.fields.location')">
                    @if ($attempt->allow_anywhere)
                        {{ __('mobile.locations.anywhere') }}
                    @else
                        {{ $attempt->location?->name ?? '—' }}
                    @endif
                </x-detail-item>
                <x-detail-item :label="__('mobile.fields.distance')">
                    @if ($attempt->distance_meters !== null)
                        <span dir="ltr">{{ $attempt->distance_meters }} m / {{ $attempt->allowed_radius_meters }} m</span>
                    @else — @endif
                </x-detail-item>
                <x-detail-item :label="__('mobile.fields.within_geofence')">{{ $attempt->within_geofence ? __('schedules.common.yes') : __('schedules.common.no') }}</x-detail-item>
                <x-detail-item :label="__('mobile.fields.mock_location')">{{ $attempt->is_mock_location ? __('schedules.common.yes') : __('schedules.common.no') }}</x-detail-item>
            </x-detail-list>
        </x-card>

        <x-card :title="__('mobile.review.time_device')">
            <x-detail-list>
                <x-detail-item :label="__('mobile.fields.server_time')"><span dir="ltr">{{ $attempt->server_time->setTimezone($tz)->format('Y-m-d H:i:s') }} ({{ $tz }})</span></x-detail-item>
                <x-detail-item :label="__('mobile.fields.device_time')"><span dir="ltr">{{ $attempt->device_time?->setTimezone($tz)->format('Y-m-d H:i:s') ?? '—' }}</span></x-detail-item>
                <x-detail-item :label="__('mobile.fields.time_drift')"><span dir="ltr">{{ $attempt->time_drift_seconds !== null ? $attempt->time_drift_seconds.' s' : '—' }}</span></x-detail-item>
                <x-detail-item :label="__('attendance.fields.shift')">{{ $attempt->shift ? $attempt->shift->code.' · '.$attempt->shift->timeRange() : __('mobile.review.no_shift_window') }}</x-detail-item>
                <x-detail-item :label="__('mobile.fields.device')"><span dir="ltr">{{ trim(($attempt->platform ?? '').' '.($attempt->app_version ?? '')) ?: '—' }}</span>
                    @if ($attempt->device_identifier)<span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $attempt->device_identifier }}</span>@endif
                </x-detail-item>
                <x-detail-item :label="__('mobile.fields.recorded')">
                    @if ($attempt->rawEvent)
                        {{ __('mobile.review.recorded_as', ['id' => $attempt->rawEvent->id]) }}
                    @else
                        {{ __('mobile.review.not_recorded') }}
                    @endif
                </x-detail-item>
            </x-detail-list>
        </x-card>
    </div>

    <x-card :title="__('mobile.fields.face')" :subtitle="__('mobile.review.face_mode', ['mode' => $attempt->face_mode->label()])" padding="none" data-region="face">
        <x-table :caption="__('mobile.fields.face')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('face.fields.attempt') }}</x-table.th>
                    <x-table.th>{{ __('face.fields.result') }}</x-table.th>
                    <x-table.th>{{ __('face.fields.score') }}</x-table.th>
                    <x-table.th>{{ __('face.fields.liveness') }}</x-table.th>
                    <x-table.th>{{ __('face.fields.provider') }}</x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($attempt->faceVerifications as $verification)
                <x-table.row>
                    <x-table.td :label="__('face.fields.attempt')" class="tabular-nums">{{ $verification->attempt_number }}</x-table.td>
                    <x-table.td :label="__('face.fields.result')"><x-badge :tone="$verification->result->tone()">{{ $verification->result->label() }}</x-badge></x-table.td>
                    <x-table.td :label="__('face.fields.score')" class="tabular-nums">{{ $verification->score ?? '—' }}</x-table.td>
                    <x-table.td :label="__('face.fields.liveness')">{{ $verification->liveness_passed === null ? '—' : ($verification->liveness_passed ? __('schedules.common.yes') : __('schedules.common.no')) }}</x-table.td>
                    <x-table.td :label="__('face.fields.provider')">{{ __('face.providers.'.$verification->provider) }} @if ($verification->is_simulated) @include('admin.attendance._simulated') @endif</x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="5" icon="camera" :title="__('mobile.review.no_face')" />
            @endforelse
        </x-table>
    </x-card>

    @if ($recent->isNotEmpty())
        <x-card :title="__('mobile.review.recent')" padding="none">
            <x-table :caption="__('mobile.review.recent')">
                @foreach ($recent as $other)
                    <x-table.row data-href="{{ route('admin.attendance.reviews.show', $other) }}">
                        <x-table.td :label="__('attendance.fields.time')" class="tabular-nums"><span dir="ltr">{{ $other->server_time->setTimezone($tz)->format('Y-m-d H:i') }}</span></x-table.td>
                        <x-table.td :label="__('attendance.fields.direction')">{{ $other->direction->label() }}</x-table.td>
                        <x-table.td :label="__('mobile.fields.reasons')">{{ collect($other->reasonCodes())->map(fn ($code) => AttemptReason::label($code))->implode(', ') ?: '—' }}</x-table.td>
                        <x-table.td :label="__('mobile.fields.outcome')">@include('admin.attendance.reviews._outcome', ['attempt' => $other])</x-table.td>
                    </x-table.row>
                @endforeach
            </x-table>
        </x-card>
    @endif

    @if ($attempt->reviewed_at)
        <x-card :title="__('mobile.review.decision')">
            <p class="text-body-md">{{ __('mobile.review.decided_by', ['name' => $attempt->reviewer?->name, 'date' => $attempt->reviewed_at->setTimezone($tz)->format('Y-m-d H:i')]) }}</p>
            @if ($attempt->review_note)<p class="mt-2 text-body-md text-text-secondary">{{ $attempt->review_note }}</p>@endif
        </x-card>
    @endif

    @if (auth()->user()->can('approve', $attempt) || auth()->user()->can('reject', $attempt))
        <x-card :title="__('mobile.review.decide')" :subtitle="__('mobile.review.decide_help')" data-region="decide">
            <div class="grid gap-4 md:grid-cols-2">
                @can('approve', $attempt)
                    <form method="POST" action="{{ route('admin.attendance.reviews.decide', [$attempt, 'approve']) }}" class="flex flex-col gap-3"
                        data-confirm="{{ __('mobile.review.confirm_approve') }}" data-confirm-label="{{ __('attendance.common.approve') }}">
                        @csrf
                        <x-form.textarea name="note" :id="'approve-note'" :label="__('mobile.review.note')" maxlength="1000" rows="2" />
                        <div><x-button icon="check">{{ __('attendance.common.approve') }}</x-button></div>
                    </form>
                @endcan
                @can('reject', $attempt)
                    <form method="POST" action="{{ route('admin.attendance.reviews.decide', [$attempt, 'reject']) }}" class="flex flex-col gap-3" data-validate
                        data-confirm="{{ __('mobile.review.confirm_reject') }}" data-confirm-tone="danger" data-confirm-label="{{ __('attendance.common.reject') }}">
                        @csrf
                        <x-form.textarea name="note" :id="'reject-note'" :label="__('mobile.review.reject_reason')" required maxlength="1000" rows="2" />
                        <div><x-button variant="danger" icon="x">{{ __('attendance.common.reject') }}</x-button></div>
                    </form>
                @endcan
            </div>
        </x-card>
    @endif
@endsection
