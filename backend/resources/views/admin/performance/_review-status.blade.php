{{-- Review status badge. Variables: $status (PerformanceReviewStatus). --}}
<x-badge :tone="$status->tone()" dot>{{ $status->label() }}</x-badge>
