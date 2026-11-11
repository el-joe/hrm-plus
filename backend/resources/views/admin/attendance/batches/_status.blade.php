{{-- Variables: $batch. --}}
@php
    $state = match ($batch->status->value) { 'draft' => 'draft', 'pending_approval' => 'pending', 'applied' => 'approved', 'rejected' => 'rejected', default => 'draft' };
@endphp
<x-status-badge :state="$state">{{ $batch->status->label() }}</x-status-badge>
