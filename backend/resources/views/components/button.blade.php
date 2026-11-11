{{--
    Button / link button.
    Props: variant (primary | secondary | ghost | danger), size (sm 32 | md 40 | lg 48),
           href (renders <a>), type (submit default), icon, icon-end, loading (server-side
           initial state; forms get it automatically on submit through resources/js/ui/forms.js).
    Any other attribute (form, name, value, data-*) is passed through.
--}}
@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
    'icon' => null,
    'iconEnd' => null,
    'loading' => false,
])

@php
    $variants = [
        'primary' => 'bg-action-primary-default text-text-on-primary hover:bg-action-primary-hover active:bg-action-primary-pressed shadow-xs',
        'secondary' => 'bg-surface-default text-text-primary border border-border-default hover:bg-action-neutral-hover active:bg-action-neutral-pressed shadow-xs',
        'ghost' => 'bg-transparent text-text-secondary hover:bg-action-neutral-hover hover:text-text-primary active:bg-action-neutral-pressed',
        'danger' => 'bg-action-danger-default text-text-on-primary hover:bg-action-danger-hover active:bg-action-danger-pressed shadow-xs',
    ];

    $sizes = [
        'sm' => 'h-8 px-3 gap-1.5 text-label-sm',
        'md' => 'h-10 px-4 gap-2 text-label-md',
        'lg' => 'h-12 px-5 gap-2 text-label-lg',
    ];

    $classes = 'inline-flex items-center justify-center whitespace-nowrap rounded-md transition-colors select-none '
        .'disabled:cursor-not-allowed disabled:bg-action-primary-disabled disabled:text-text-disabled disabled:border-transparent disabled:shadow-none '
        .'aria-disabled:pointer-events-none aria-disabled:bg-action-primary-disabled aria-disabled:text-text-disabled '
        .'data-loading:cursor-progress data-loading:opacity-80 '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);

    $iconSize = $size === 'lg' ? 'md' : 'sm';

    // Directional icons are drawn for LTR and mirrored in RTL.
    $directional = ['chevron-left', 'chevron-right', 'arrow-left', 'arrow-right', 'log-out', 'log-in'];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($icon) <x-icon :name="$icon" :size="$iconSize" :flip="in_array($icon, $directional, true)" /> @endif
        <span>{{ $slot }}</span>
        @if ($iconEnd) <x-icon :name="$iconEnd" :size="$iconSize" :flip="in_array($iconEnd, $directional, true)" /> @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }} @if ($loading) data-loading aria-busy="true" @endif>
        <x-icon name="loader" :size="$iconSize" class="hidden animate-spin" data-button-spinner />
        @if ($icon) <x-icon :name="$icon" :size="$iconSize" :flip="in_array($icon, $directional, true)" data-button-icon /> @endif
        <span>{{ $slot }}</span>
        @if ($iconEnd) <x-icon :name="$iconEnd" :size="$iconSize" :flip="in_array($iconEnd, $directional, true)" /> @endif
    </button>
@endif
