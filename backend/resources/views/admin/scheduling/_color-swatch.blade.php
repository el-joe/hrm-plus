{{-- Shift colour dot. Variables: $color (chart-1 … chart-6). Class names are literal so Tailwind generates them. --}}
@php
    $swatches = ['chart-1' => 'bg-chart-1', 'chart-2' => 'bg-chart-2', 'chart-3' => 'bg-chart-3', 'chart-4' => 'bg-chart-4', 'chart-5' => 'bg-chart-5', 'chart-6' => 'bg-chart-6'];
@endphp
<span class="inline-block size-3 shrink-0 rounded-full {{ $swatches[$color] ?? 'bg-chart-1' }}" aria-hidden="true"></span>
