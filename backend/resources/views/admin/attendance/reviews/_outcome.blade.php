{{-- Outcome (+ review state) of a mobile attempt. Variables: $attempt. --}}
<x-badge :tone="$attempt->outcome->tone()" dot>{{ $attempt->outcome->label() }}</x-badge>
@if ($attempt->review_status)
    <x-badge :tone="$attempt->review_status->tone()">{{ $attempt->review_status->label() }}</x-badge>
@endif
