{{-- One-line description of a component's rule. Variables: $component (SalaryComponent, baseComponent loaded). --}}
@php($type = $component->calculation_type)
<span class="block">{{ $type->label() }}</span>
<span class="block text-caption-md text-text-tertiary" dir="auto">
    @if ($type === App\Enums\PayCalculationType::Fixed)
        <span dir="ltr">{{ App\Support\Money::format($component->amount) }}</span>
    @elseif ($type === App\Enums\PayCalculationType::Percentage)
        {{ __('payroll.common.percent_of', ['rate' => App\Services\Payroll\PayrollMath::trim($component->rate), 'base' => $component->baseComponent?->name ?? __('payroll.common.basic_salary')]) }}
    @elseif ($type->isQuantityBased())
        @if ($component->amount !== null)
            <span dir="ltr">{{ App\Support\Money::format($component->amount) }}</span>
        @elseif ($component->use_policy_multiplier)
            {{ __('payroll.fields.use_policy_multiplier') }}
        @else
            <span dir="ltr">× {{ App\Services\Payroll\PayrollMath::trim($component->rate) ?? ($type === App\Enums\PayCalculationType::OvertimeHours ? setting('payroll.overtime_rate_multiplier') : '1') }}</span>
        @endif
    @elseif ($type === App\Enums\PayCalculationType::Statutory)
        {{ $component->statutory_type?->label() }}
    @endif
</span>
