{{--
    Menu item: a link (href), or a form button (action + method, for POST/DELETE such as logout).
    Props: href, action, method, icon, tone (default | danger). Extra attributes (data-confirm, ...)
    land on the <a> or on the <form>.
--}}
@props(['href' => null, 'action' => null, 'method' => 'POST', 'icon' => null, 'tone' => 'default'])

@php
    $classes = 'flex w-full items-center gap-2.5 rounded-md px-3 py-2 text-start text-body-md transition-colors '
        .($tone === 'danger' ? 'text-feedback-danger-text hover:bg-action-danger-subtle' : 'text-text-primary hover:bg-action-neutral-hover');
@endphp

@if ($action)
    <form method="POST" action="{{ $action }}" {{ $attributes }}>
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <button type="submit" role="menuitem" class="{{ $classes }}">
            @if ($icon) <x-icon :name="$icon" size="sm" /> @endif
            {{ $slot }}
        </button>
    </form>
@else
    <a href="{{ $href }}" role="menuitem" {{ $attributes->class($classes) }}>
        @if ($icon) <x-icon :name="$icon" size="sm" /> @endif
        {{ $slot }}
    </a>
@endif
