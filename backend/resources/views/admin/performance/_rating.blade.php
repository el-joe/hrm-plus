{{-- Final rating band. Variables: $level (?RatingScaleLevel), optional $calibrated (bool). Colour classes are literal for Tailwind. --}}
@if ($level)
    <span class="inline-flex items-center gap-1.5 text-label-md text-text-primary">
        <span @class([
            'inline-block size-2.5 shrink-0 rounded-full',
            'bg-chart-1' => $level->color === 'chart-1',
            'bg-chart-2' => $level->color === 'chart-2',
            'bg-chart-3' => $level->color === 'chart-3',
            'bg-chart-4' => $level->color === 'chart-4',
            'bg-chart-5' => $level->color === 'chart-5',
            'bg-chart-6' => $level->color === 'chart-6',
        ]) aria-hidden="true"></span>
        {{ $level->label }}
        @if ($calibrated ?? false)
            <x-badge tone="warning">{{ __('performance.reviews.calibrated') }}</x-badge>
        @endif
    </span>
@else
    <span class="text-text-tertiary">—</span>
@endif
