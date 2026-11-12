{{-- A score on the cycle scale ("3.75 / 5"). Variables: $score (?string), $max (?string). --}}
@if ($score !== null)
    <span class="tabular-nums" dir="ltr">{{ $score }}@if ($max) <span class="text-text-tertiary">/ {{ rtrim(rtrim((string) $max, '0'), '.') }}</span>@endif</span>
@else
    <span class="text-text-tertiary">—</span>
@endif
