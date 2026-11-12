{{--
    A date / date-time / time in the configured display format (Settings → Localisation:
    date format, time format, Arabic-Indic digits) through App\Support\Formatter.
    Props: value (Carbon | string | null), format date|datetime|time, timezone (instants), empty (text when null).
--}}
@props(['value' => null, 'format' => 'date', 'timezone' => null, 'empty' => '—'])

@php
    $text = match ($format) {
        'datetime' => \App\Support\Formatter::dateTime($value, $timezone),
        'time' => \App\Support\Formatter::time($value, $timezone),
        default => \App\Support\Formatter::date($value),
    };
    $machine = $value instanceof \DateTimeInterface ? ($format === 'date' ? $value->format('Y-m-d') : $value->format(DATE_ATOM)) : $value;
@endphp

@if ($text === '')
    <span {{ $attributes }}>{{ $empty }}</span>
@else
    <time datetime="{{ $machine }}" dir="ltr" {{ $attributes->class('tabular-nums') }}>{{ $text }}</time>
@endif
