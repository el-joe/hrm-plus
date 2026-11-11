{{--
    Initials avatar (no uploads exist yet). Props: name, size (sm 32 | md 40 | lg 56 | xl 80), src (optional image URL).
--}}
@props(['name' => '', 'size' => 'md', 'src' => null])

@php
    $sizes = ['sm' => 'size-8 text-label-sm', 'md' => 'size-10 text-label-md', 'lg' => 'size-14 text-h4', 'xl' => 'size-20 text-h2'];

    $initials = collect(preg_split('/\s+/u', trim((string) $name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn (string $word): string => mb_substr($word, 0, 1))
        ->implode('');
@endphp

@if ($src)
    <img src="{{ $src }}" alt="" {{ $attributes->class('shrink-0 rounded-full object-cover '.($sizes[$size] ?? $sizes['md'])) }}>
@else
    <span aria-hidden="true" {{ $attributes->class('inline-flex shrink-0 items-center justify-center rounded-full bg-action-primary-subtle text-text-link '.($sizes[$size] ?? $sizes['md'])) }}>{{ mb_strtoupper($initials) }}</span>
@endif
