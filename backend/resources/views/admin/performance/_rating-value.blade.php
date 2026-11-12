{{-- A given rating with its level label. Variables: $value (?string), $levels. --}}
@if ($value !== null)
    @php($match = $levels->first(fn ($level) => bccomp((string) $level->value, (string) $value, 2) === 0))
    <span class="tabular-nums" dir="ltr">{{ rtrim(rtrim((string) $value, '0'), '.') }}</span>@if ($match) <span class="text-text-tertiary">· {{ $match->label }}</span>@endif
@else
    <span class="text-text-tertiary">—</span>
@endif
