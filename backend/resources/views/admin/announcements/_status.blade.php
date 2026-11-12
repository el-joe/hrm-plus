{{-- Derived status badge of an announcement. Variables: $announcement. --}}
@php($state = $announcement->state())
<x-badge :tone="$state->tone()" dot>{{ $state->label() }}</x-badge>
