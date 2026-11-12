{{--
    A number or amount in the configured digits (Arabic-Indic when the setting is on and the
    interface is Arabic) through App\Support\Formatter. Amounts are DECIMAL strings (bcmath, never float).
    Props: value, decimals (numbers), money (bool: currency decimals from the settings), percent (bool), empty.
--}}
@props(['value' => null, 'decimals' => 0, 'money' => false, 'percent' => false, 'empty' => '—'])

@php
    $text = match (true) {
        $value === null || $value === '' => '',
        $money => \App\Support\Formatter::money((string) $value),
        $percent => \App\Support\Formatter::percent($value, $decimals),
        default => \App\Support\Formatter::number($value, $decimals),
    };
@endphp

<span dir="ltr" {{ $attributes->class('tabular-nums') }}>{{ $text === '' ? $empty : $text }}</span>
