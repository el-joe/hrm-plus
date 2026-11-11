{{--
    Workflow / attendance status pill with a dot (status/* tokens).
    Props: state (present | absent | late | on-leave | pending | approved | rejected | finalized | draft).
    The slot is the translated label.
--}}
@props(['state' => 'draft'])

@php
    $states = [
        'present' => ['bg-status-present-bg text-status-present-text', 'bg-status-present-dot'],
        'absent' => ['bg-status-absent-bg text-status-absent-text', 'bg-status-absent-dot'],
        'late' => ['bg-status-late-bg text-status-late-text', 'bg-status-late-dot'],
        'on-leave' => ['bg-status-on-leave-bg text-status-on-leave-text', 'bg-status-on-leave-dot'],
        'pending' => ['bg-status-pending-bg text-status-pending-text', 'bg-status-pending-dot'],
        'approved' => ['bg-status-approved-bg text-status-approved-text', 'bg-status-approved-dot'],
        'rejected' => ['bg-status-rejected-bg text-status-rejected-text', 'bg-status-rejected-dot'],
        'finalized' => ['bg-status-finalized-bg text-status-finalized-text', 'bg-status-finalized-dot'],
        'draft' => ['bg-status-draft-bg text-status-draft-text', 'bg-status-draft-dot'],
    ];

    [$pill, $dot] = $states[$state] ?? $states['draft'];
@endphp

<span {{ $attributes->class('inline-flex h-6 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 text-label-sm '.$pill) }} data-status="{{ $state }}">
    <span class="size-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>
    {{ $slot }}
</span>
