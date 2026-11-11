{{-- Progress bar. Variables: $value (0–100), $label --}}
<div class="flex items-center gap-2">
    <div class="h-2 w-full min-w-20 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $value }}" aria-label="{{ $label }}">
        <div @class(['h-full rounded-full', 'bg-feedback-success-solid' => $value === 100, 'bg-action-primary-default' => $value < 100]) style="inline-size: {{ $value }}%"></div>
    </div>
    <span class="w-10 shrink-0 text-end text-label-sm tabular-nums text-text-secondary">{{ $value }}%</span>
</div>
