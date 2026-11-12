{{-- Goal progress bar. Variables: $value (DECIMAL string 0–100), $label. --}}
@php($shown = rtrim(rtrim((string) $value, '0'), '.') ?: '0')
<div class="flex items-center gap-2">
    <div class="h-2 w-full min-w-20 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $shown }}" aria-label="{{ $label }}">
        <div @class(['h-full rounded-full', 'bg-feedback-success-solid' => bccomp((string) $value, '100', 2) === 0, 'bg-action-primary-default' => bccomp((string) $value, '100', 2) !== 0]) style="inline-size: {{ $shown }}%"></div>
    </div>
    <span class="w-12 shrink-0 text-end text-label-sm tabular-nums text-text-secondary" dir="ltr">{{ $shown }}%</span>
</div>
