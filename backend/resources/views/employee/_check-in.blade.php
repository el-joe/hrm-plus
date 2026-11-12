{{--
    Check-in / check-out from the browser (Employee Home and My Attendance).
    Variables: $checkIn (App\Services\Attendance\Mobile\PortalCheckIn::card()), optional $checkInId (unique per page).
    Behaviour: resources/js/pages/check-in.js — browser location (+ camera when the policy asks for a face),
    POST portal.attendance.check-in|check-out (JSON), success only on 201. Without JavaScript only the state is shown.
--}}
@php
    $checkInId ??= 'check-in';
    $digits = fn ($text): string => \App\Support\Formatter::digits((string) $text);
    $face = $checkIn['face'];
    $action = $checkIn['next_action'];
    $config = [
        'action' => $action,
        'urls' => ['check_in' => route('portal.attendance.check-in'), 'check_out' => route('portal.attendance.check-out')],
        'requires_location' => $checkIn['requires_location'],
        'max_accuracy' => $checkIn['max_accuracy_meters'],
        'face' => ['capture' => $face['capture'], 'optional' => $face['optional']],
        'camera_id' => $checkInId.'-camera',
        'strings' => [
            ...trans('mobile.web.messages'),
            'accuracy' => __('mobile.web.chips.accuracy'),
            'retry' => __('mobile.web.retry'),
            'action' => __('mobile.web.'.$action),
        ],
    ];
@endphp

<div class="mt-4 flex flex-col gap-3 rounded-lg border border-border-subtle bg-surface-subtle p-3 sm:p-4" data-check-in id="{{ $checkInId }}">
    <script type="application/json" data-check-in-config>{!! json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>

    @if ($checkIn['blocked'])
        <p class="flex items-start gap-2 text-body-sm text-text-secondary" data-check-in-blocked="{{ $checkIn['blocked'] }}">
            <x-icon name="info" size="sm" class="mt-0.5 shrink-0 text-text-tertiary" />
            <span>{{ $checkIn['blocked_message'] }}</span>
        </p>
    @else
        {{-- Status chips: location rule, live accuracy, shift window, face. --}}
        <div class="flex flex-wrap items-center gap-2" data-check-in-chips>
            <x-badge tone="neutral"><x-icon name="map-pin" size="sm" />
                {{ $checkIn['allow_anywhere'] ? __('mobile.web.chips.location_anywhere') : ($checkIn['requires_location'] ? __('mobile.web.chips.location_geofence') : __('mobile.web.chips.location_recorded')) }}</x-badge>
            <x-badge tone="info" hidden data-check-in-accuracy></x-badge>
            @if ($checkIn['within_shift_window'] && $checkIn['window'])
                <x-badge tone="success"><x-icon name="clock" size="sm" /><span dir="auto">{{ $digits(__('mobile.web.chips.window_open', ['time' => $checkIn['window']['closes_local']])) }}</span></x-badge>
            @elseif ($checkIn['window'])
                <x-badge tone="warning"><x-icon name="clock" size="sm" /><span dir="auto">{{ $digits(__('mobile.web.chips.window_opens', ['time' => $checkIn['window']['opens_local']])) }}</span></x-badge>
            @else
                <x-badge tone="warning"><x-icon name="clock" size="sm" />{{ __('mobile.web.chips.window_none') }}</x-badge>
            @endif
            <x-badge :tone="$face['mode'] === 'required' ? 'primary' : 'neutral'"><x-icon name="camera" size="sm" />{{ __('mobile.web.chips.face.'.$face['mode']) }}</x-badge>
            @if ($face['mode'] !== 'off' && ! $face['enrolled'])
                <x-badge tone="warning">{{ __('mobile.web.chips.face_not_enrolled') }}</x-badge>
            @elseif ($face['mode'] === 'required')
                <x-badge tone="neutral">{{ $digits(__('mobile.web.chips.face_attempts', ['count' => $face['remaining_attempts']])) }}</x-badge>
            @endif
            @if ($face['capture'] && $face['is_simulated'])
                @include('admin.attendance._simulated')
            @endif
        </div>

        @if (! $checkIn['within_shift_window'])
            <p class="text-body-sm text-text-secondary" data-check-in-note="window">{{ __('mobile.web.notes.outside_window_'.$checkIn['outside_window_action']) }}</p>
        @endif
        @if ($face['mode'] === 'required' && ! $face['enrolled'])
            <p class="text-body-sm text-text-secondary" data-check-in-note="face">{{ __('mobile.web.notes.face_not_enrolled_review') }}</p>
        @endif
        @if ($checkIn['requires_location'] && ! $checkIn['allow_anywhere'] && $checkIn['location_names'] !== [])
            <p class="text-caption-md text-text-tertiary" dir="auto">{{ __('mobile.web.notes.locations', ['names' => implode('، ', $checkIn['location_names'])]) }}</p>
        @endif

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center" hidden data-js-only>
            <x-button type="button" size="lg" :icon="$action === 'check_in' ? 'log-in' : 'log-out'" class="w-full sm:w-auto" data-check-in-submit>
                {{ __('mobile.web.'.$action) }}
            </x-button>
        </div>
        <noscript><p class="text-body-sm text-text-secondary">{{ __('mobile.web.no_js') }}</p></noscript>

        <p class="hidden rounded-md px-3 py-2 text-body-sm" role="status" aria-live="polite" data-check-in-status></p>

        <x-modal :id="$checkInId.'-camera'" :title="__('mobile.web.camera.title')" size="sm" data-check-in-camera>
            <p class="mb-3 text-body-sm text-text-secondary">{{ __('mobile.web.camera.help') }}</p>
            <div class="relative overflow-hidden rounded-lg bg-surface-inverse">
                <video class="aspect-[4/3] w-full -scale-x-100 object-cover" autoplay playsinline muted data-check-in-video></video>
                <p class="absolute inset-0 flex items-center justify-center p-4 text-center text-body-sm text-text-inverse" data-check-in-camera-status>{{ __('mobile.web.camera.starting') }}</p>
            </div>
            <canvas class="hidden" data-check-in-canvas></canvas>
            <x-slot:footer>
                <x-button type="button" variant="ghost" data-check-in-camera-cancel>{{ __('mobile.web.camera.cancel') }}</x-button>
                @if ($face['optional'])
                    <x-button type="button" variant="secondary" data-check-in-camera-skip>{{ __('mobile.web.camera.skip') }}</x-button>
                @endif
                <x-button type="button" icon="camera" disabled data-check-in-capture>{{ __('mobile.web.camera.capture') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endif

    @if ($checkIn['pending_reviews'])
        <p class="text-body-sm text-text-secondary">{{ $digits(trans_choice('mobile.web.notes.pending_reviews', $checkIn['pending_reviews'], ['count' => $checkIn['pending_reviews']])) }}</p>
    @endif
</div>
