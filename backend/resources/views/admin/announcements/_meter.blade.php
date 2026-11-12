{{--
    Percentage bar (CSS only). Variables: $percent (0–100), $tone ('primary'|'success'), $label (accessible text).
--}}
<div class="flex h-2 w-full overflow-hidden rounded-full bg-surface-muted" role="img" aria-label="{{ $label }}">
    @if ($percent > 0)
        <span @class(['h-full', 'bg-action-primary-default' => ($tone ?? 'primary') === 'primary', 'bg-status-present-dot' => ($tone ?? 'primary') === 'success'])
            style="inline-size: {{ min(100, $percent) }}%"></span>
    @endif
</div>
