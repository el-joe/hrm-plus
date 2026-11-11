{{--
    Inline message. Props: tone (success | warning | danger | info), title, dismissible.
    Dismissal is handled by data-dismiss (resources/js/ui/dismiss.js); without JS the button is hidden.
--}}
@props(['tone' => 'info', 'title' => null, 'dismissible' => false])

@php
    $tones = [
        'success' => ['bg-feedback-success-bg border-feedback-success-border text-feedback-success-text', 'check-circle'],
        'warning' => ['bg-feedback-warning-bg border-feedback-warning-border text-feedback-warning-text', 'alert-triangle'],
        'danger' => ['bg-feedback-danger-bg border-feedback-danger-border text-feedback-danger-text', 'x-circle'],
        'info' => ['bg-feedback-info-bg border-feedback-info-border text-feedback-info-text', 'info'],
    ];
    [$classes, $icon] = $tones[$tone] ?? $tones['info'];
@endphp

<div role="{{ in_array($tone, ['danger', 'warning'], true) ? 'alert' : 'status' }}" data-alert
    {{ $attributes->class('flex items-start gap-3 rounded-lg border p-4 '.$classes) }}>
    <x-icon :name="$icon" class="mt-0.5" />
    <div class="min-w-0 flex-1 text-body-sm">
        @if ($title)
            <p class="text-label-md">{{ $title }}</p>
        @endif
        <div>{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button type="button" class="-m-1 rounded-sm p-1 opacity-70 hover:opacity-100" data-dismiss="[data-alert]" aria-label="{{ __('app.ui.close') }}" hidden data-js-only>
            <x-icon name="x" size="sm" />
        </button>
    @endif
</div>
