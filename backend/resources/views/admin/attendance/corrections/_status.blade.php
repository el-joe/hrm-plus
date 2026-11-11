{{-- Variables: $correction. --}}
@php
    $state = match ($correction->status->value) { 'pending' => 'pending', 'applied' => 'approved', 'rejected' => 'rejected', default => 'draft' };
@endphp
<x-status-badge :state="$state">{{ $correction->status->label() }}</x-status-badge>
