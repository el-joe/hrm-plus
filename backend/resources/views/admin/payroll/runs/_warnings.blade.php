{{-- Warning badges of a payroll item. Variables: $warnings (list of {code, params, blocking}), optional $full (bool: render as a list with text). --}}
@if (! empty($full))
    <ul class="flex flex-col gap-2">
        @foreach ($warnings as $warning)
            <li class="flex items-start gap-2 text-body-sm {{ $warning['blocking'] ? 'text-feedback-danger-text' : 'text-text-secondary' }}">
                <x-icon :name="$warning['blocking'] ? 'x-circle' : 'alert-triangle'" size="sm" class="mt-0.5 shrink-0" />
                <span>{{ __('payroll_runs.warnings.'.$warning['code'], array_map(fn ($value) => is_string($value) && str_starts_with($warning['code'], 'statutory_missing') ? __('payroll.enums.statutory_types.'.$value) : $value, $warning['params'])) }}</span>
            </li>
        @endforeach
    </ul>
@else
    <div class="flex flex-wrap gap-1">
        @foreach ($warnings as $warning)
            <x-badge :tone="$warning['blocking'] ? 'danger' : ($warning['code'] === 'demo_statutory' ? 'warning' : 'neutral')">{{ __('payroll_runs.warnings.short.'.$warning['code']) }}</x-badge>
        @endforeach
    </div>
@endif
