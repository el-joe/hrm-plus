{{-- Session dates (LTR) and times. Variables: $session. --}}
<span dir="ltr" class="tabular-nums whitespace-nowrap">{{ $session->start_date->toDateString() }}@if (! $session->end_date->isSameDay($session->start_date)) → {{ $session->end_date->toDateString() }}@endif</span>
@if ($session->start_time)
    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ substr((string) $session->start_time, 0, 5) }}–{{ substr((string) $session->end_time, 0, 5) }}</span>
@endif
