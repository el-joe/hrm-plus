{{-- Approval actions of a record, newest run first. Variables: $requests (Collection<ApprovalRequest> with actions.actor, currentStep). --}}
<ol class="flex flex-col gap-3">
    @forelse ($requests as $approval)
        @foreach ($approval->actions as $action)
            <li class="flex flex-col gap-0.5 border-s-2 border-border-subtle ps-3">
                <span class="text-label-md text-text-primary">{{ $action->action->label() }}@if ($action->step_name) · {{ $action->step_name }}@endif</span>
                <span class="text-caption-md text-text-tertiary">{{ $action->actor?->name ?? '—' }} · <span dir="ltr">{{ $action->created_at?->format('Y-m-d H:i') }}</span></span>
                @if ($action->reason)
                    <span class="text-body-sm text-text-secondary">{{ $action->reason }}</span>
                @endif
            </li>
        @endforeach
        @if ($approval->isPending() && $approval->currentStep)
            <li class="border-s-2 border-status-pending-dot ps-3 text-caption-md text-text-secondary">{{ __('payroll.common.at_step', ['step' => $approval->currentStep->name]) }}</li>
        @endif
    @empty
        <li class="text-body-sm text-text-tertiary">{{ __('payroll.common.no_history') }}</li>
    @endforelse
</ol>
