{{-- Certificate validity badge. Variables: $certificate, optional $reminderDays. --}}
@php($state = $certificate->validityState($reminderDays ?? null))
<x-badge :tone="match ($state) { 'expired' => 'danger', 'expiring' => 'warning', 'valid' => 'success', default => 'neutral' }" dot>{{ __('training.validity.'.$state) }}</x-badge>
