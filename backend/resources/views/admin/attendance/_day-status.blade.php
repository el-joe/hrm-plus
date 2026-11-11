{{-- Status pill of a processed day + "manual" marker. Variables: $day (AttendanceDay). --}}
<span class="inline-flex flex-wrap items-center gap-1">
    <x-status-badge :state="$day->status->badge()">{{ $day->statusLabel() }}</x-status-badge>
    @if ($day->is_manual)
        <x-badge tone="info" data-manual-marker>{{ __('attendance.common.manual') }}</x-badge>
    @endif
    @if ($day->isLocked())
        <x-icon name="lock" size="sm" class="text-text-tertiary" aria-label="{{ __('attendance.common.locked') }}" />
    @endif
</span>
