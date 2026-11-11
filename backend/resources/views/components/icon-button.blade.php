{{--
    Square icon-only button (or link). The label is required: it becomes the
    accessible name and the tooltip.
    Props: icon, label, variant (ghost | secondary | primary | danger), size (sm 32 | md 40), href, type (button default).
--}}
@props(['icon', 'label', 'variant' => 'ghost', 'size' => 'md', 'href' => null, 'type' => 'button', 'flip' => false])

@php
    $variants = [
        'ghost' => 'text-text-secondary hover:bg-action-neutral-hover hover:text-text-primary',
        'secondary' => 'border border-border-default bg-surface-default text-text-secondary hover:bg-action-neutral-hover hover:text-text-primary',
        'primary' => 'bg-action-primary-default text-text-on-primary hover:bg-action-primary-hover',
        'danger' => 'text-feedback-danger-text hover:bg-action-danger-subtle',
    ];

    $classes = 'inline-flex shrink-0 items-center justify-center rounded-md transition-colors disabled:cursor-not-allowed disabled:text-text-disabled '
        .($size === 'sm' ? 'size-8 ' : 'size-10 ').($variants[$variant] ?? $variants['ghost']);
@endphp

@if ($href)
    <a href="{{ $href }}" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-icon :name="$icon" :size="$size === 'sm' ? 'sm' : 'md'" :flip="$flip" />
    </a>
@else
    <button type="{{ $type }}" aria-label="{{ $label }}" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-icon :name="$icon" :size="$size === 'sm' ? 'sm' : 'md'" :flip="$flip" />
    </button>
@endif
