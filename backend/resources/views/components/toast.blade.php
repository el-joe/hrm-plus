{{-- Single toast. Props: tone (success | warning | danger | info), title. --}}
@props(['tone' => 'info', 'title' => null])

@php
    $tones = [
        'success' => ['text-feedback-success-solid', 'check-circle'],
        'warning' => ['text-feedback-warning-solid', 'alert-triangle'],
        'danger' => ['text-feedback-danger-solid', 'x-circle'],
        'info' => ['text-feedback-info-solid', 'info'],
    ];
    [$color, $icon] = $tones[$tone] ?? $tones['info'];
@endphp

<div role="{{ $tone === 'danger' ? 'alert' : 'status' }}" data-toast data-tone="{{ $tone }}"
    class="pointer-events-auto flex w-full items-start gap-3 rounded-lg border border-border-subtle bg-surface-raised p-4 shadow-lg">
    <span class="{{ $color }}" data-toast-icon>
        @foreach ($tones as $name => [$toneColor, $toneIcon])
            <x-icon :name="$toneIcon" data-toast-icon-tone="{{ $name }}" :hidden="$name !== $tone" />
        @endforeach
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-label-md text-text-primary" data-toast-title @if (! $title) hidden @endif>{{ $title }}</p>
        <p class="text-body-sm text-text-secondary" data-toast-message>{{ $slot }}</p>
    </div>
    <button type="button" class="-m-1 rounded-sm p-1 text-text-tertiary hover:text-text-primary" data-toast-close aria-label="{{ __('app.ui.close') }}" hidden data-js-only>
        <x-icon name="x" size="sm" />
    </button>
</div>
