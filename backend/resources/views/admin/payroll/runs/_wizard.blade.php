{{-- Wizard progress. Variable: $current (1 scope, 2 preview, 3 calculate & review, 4 approval, 5 finalise). --}}
@php($steps = ['scope', 'preview', 'review', 'approval', 'finalise'])
<ol class="flex flex-wrap gap-2" aria-label="{{ __('payroll_runs.wizard.label') }}">
    @foreach ($steps as $index => $step)
        @php($number = $index + 1)
        <li @class([
            'flex items-center gap-2 rounded-full border px-3 py-1 text-label-sm',
            'border-action-primary-default bg-action-primary-default text-text-on-primary' => $number === $current,
            'border-border-subtle bg-feedback-success-bg text-feedback-success-text' => $number < $current,
            'border-border-subtle text-text-tertiary' => $number > $current,
        ]) @if ($number === $current) aria-current="step" @endif>
            <span class="tabular-nums">{{ $number }}</span> {{ __('payroll_runs.wizard.'.$step) }}
        </li>
    @endforeach
</ol>
