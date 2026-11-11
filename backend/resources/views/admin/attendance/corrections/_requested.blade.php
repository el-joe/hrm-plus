{{-- What a correction asks for. Variables: $correction; optional $timezone. --}}
@php
    $zone = $timezone ?? app(\App\Services\Attendance\AttendanceClock::class)->timezoneForBranch($correction->employee?->branch_id);
@endphp
<ul class="flex flex-col gap-0.5 text-body-sm">
    @if ($correction->check_in_at)
        <li>{{ __('attendance.fields.check_in') }}: <span dir="ltr" class="tabular-nums">{{ $correction->check_in_at->setTimezone($zone)->format('Y-m-d H:i') }}</span></li>
    @endif
    @if ($correction->check_out_at)
        <li>{{ __('attendance.fields.check_out') }}: <span dir="ltr" class="tabular-nums">{{ $correction->check_out_at->setTimezone($zone)->format('Y-m-d H:i') }}</span></li>
    @endif
    @if ($correction->attendanceStatus)
        <li>{{ __('attendance.fields.status') }}: {{ $correction->attendanceStatus->name }}</li>
    @endif
    @if ($correction->void_raw_event_ids)
        <li>{{ trans_choice('attendance.corrections.void_count', count($correction->void_raw_event_ids), ['count' => count($correction->void_raw_event_ids)]) }}</li>
    @endif
</ul>
