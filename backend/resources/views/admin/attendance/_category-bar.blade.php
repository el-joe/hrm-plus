{{--
    Stacked CSS bar of the dashboard categories (no chart library). Variables: $row (expected + one count per category).
--}}
@php
    $colours = ['present' => 'bg-status-present-dot', 'late' => 'bg-status-late-dot', 'on_leave' => 'bg-status-on-leave-dot', 'absent' => 'bg-status-absent-dot', 'not_in' => 'bg-status-draft-dot'];
@endphp
<div class="flex h-2.5 w-full overflow-hidden rounded-full bg-surface-muted" role="img"
    aria-label="{{ collect($colours)->keys()->map(fn ($key) => __('attendance_reports.categories.'.$key).': '.$row[$key])->implode(', ') }}">
    @foreach ($colours as $key => $class)
        @if ($row['expected'] > 0 && $row[$key] > 0)
            <span class="{{ $class }} h-full" style="inline-size: {{ round($row[$key] * 100 / $row['expected'], 2) }}%"></span>
        @endif
    @endforeach
</div>
