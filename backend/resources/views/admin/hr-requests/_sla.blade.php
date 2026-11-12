{{-- Due time / overdue / escalation markers of a request. Variables: $hrRequest. --}}
@if ($hrRequest->due_at && ! $hrRequest->isClosed())
    <span @class(['block text-caption-md', 'text-feedback-danger-text' => $hrRequest->isOverdue(), 'text-text-tertiary' => ! $hrRequest->isOverdue()])>
        @if ($hrRequest->isOverdue())
            <x-icon name="alert-triangle" size="sm" class="inline" /> {{ __('hr_requests.common.overdue') }} ·
        @endif
        <span dir="ltr">{{ $hrRequest->due_at->timezone(setting('localisation.timezone'))->format('Y-m-d H:i') }}</span>
    </span>
@endif
@if ($hrRequest->escalation_level > 0 && ! $hrRequest->isClosed())
    <x-badge tone="danger">{{ __('hr_requests.common.escalated', ['level' => $hrRequest->escalation_level]) }}</x-badge>
@endif
