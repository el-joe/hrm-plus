{{--
    KPI card. Values must come from the controller; never hardcode numbers.
    Props: label, value, icon, href (makes the card a link), trend (up | down | neutral),
           trend-value (e.g. "4.2%"), hint (caption under the value).
--}}
@props(['label', 'value', 'icon' => null, 'href' => null, 'trend' => null, 'trendValue' => null, 'hint' => null])

@php
    $trendClasses = [
        'up' => 'bg-feedback-success-bg text-feedback-success-text',
        'down' => 'bg-feedback-danger-bg text-feedback-danger-text',
        'neutral' => 'bg-surface-muted text-text-secondary',
    ];
    $trendIcons = ['up' => 'trend-up', 'down' => 'trend-down', 'neutral' => 'minus'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class(['flex flex-col gap-3 rounded-xl border border-border-subtle bg-surface-default p-5 shadow-xs', 'transition-shadow hover:shadow-sm' => $href]) }}>
    <div class="flex items-center justify-between gap-3">
        <p class="text-label-md text-text-secondary">{{ $label }}</p>
        @if ($icon)
            <span class="inline-flex size-9 items-center justify-center rounded-md bg-action-primary-subtle text-text-link">
                <x-icon :name="$icon" />
            </span>
        @endif
    </div>
    <p class="text-numeric-xl text-text-primary" data-stat-value>{{ \App\Support\Formatter::digits($value) }}</p>
    @if ($trend || $hint)
        <div class="flex flex-wrap items-center gap-2">
            @if ($trend)
                <span class="inline-flex h-5 items-center gap-1 rounded-xs px-1.5 text-label-sm {{ $trendClasses[$trend] ?? $trendClasses['neutral'] }}">
                    <x-icon :name="$trendIcons[$trend] ?? 'minus'" size="sm" />{{ $trendValue }}
                </span>
            @endif
            @if ($hint)
                <span class="text-caption-md text-text-tertiary">{{ \App\Support\Formatter::digits($hint) }}</span>
            @endif
        </div>
    @endif
</{{ $tag }}>
