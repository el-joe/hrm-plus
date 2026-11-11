{{--
    Small label. Props: tone (neutral | primary | success | warning | danger | info), dot (bool).
--}}
@props(['tone' => 'neutral', 'dot' => false])

@php
    $tones = [
        'neutral' => 'bg-surface-muted text-text-secondary',
        'primary' => 'bg-action-primary-subtle text-text-link',
        'success' => 'bg-feedback-success-bg text-feedback-success-text',
        'warning' => 'bg-feedback-warning-bg text-feedback-warning-text',
        'danger' => 'bg-feedback-danger-bg text-feedback-danger-text',
        'info' => 'bg-feedback-info-bg text-feedback-info-text',
    ];
@endphp

<span {{ $attributes->class('inline-flex h-6 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-label-sm '.($tones[$tone] ?? $tones['neutral'])) }}>
    @if ($dot) <span class="size-1.5 rounded-full bg-current" aria-hidden="true"></span> @endif
    {{ $slot }}
</span>
