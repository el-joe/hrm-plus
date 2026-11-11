{{-- Leave type colour dot. Variables: $color (chart-1 … chart-6). Literal class names so Tailwind generates them. --}}
@php
    $leaveSwatches = ['chart-1' => 'bg-chart-1', 'chart-2' => 'bg-chart-2', 'chart-3' => 'bg-chart-3', 'chart-4' => 'bg-chart-4', 'chart-5' => 'bg-chart-5', 'chart-6' => 'bg-chart-6'];
@endphp
<span class="inline-block size-2.5 shrink-0 rounded-full {{ $leaveSwatches[$color] ?? 'bg-chart-1' }}" aria-hidden="true"></span>
