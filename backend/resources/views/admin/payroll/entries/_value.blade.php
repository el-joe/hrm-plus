{{-- Amount / rate / instalment plan of an entry. Variables: $entry (PayEntry with component, baseComponent). --}}
@if ($entry->recurrence === App\Enums\PayEntryRecurrence::Instalment)
    <span dir="ltr" class="tabular-nums">{{ App\Support\Money::format($entry->total_amount) }}</span>
    <span class="block text-caption-md text-text-tertiary">{{ __('payroll.entries.instalment_plan', ['count' => $entry->instalment_count, 'amount' => App\Support\Money::format($entry->instalmentAmount(1))]) }}</span>
@elseif ($entry->calculation === App\Enums\PayEntryCalculation::Percentage)
    {{ __('payroll.common.percent_of', ['rate' => App\Services\Payroll\PayrollMath::trim((string) $entry->rate), 'base' => $entry->baseComponent?->name ?? __('payroll.common.basic_salary')]) }}
@else
    <span dir="ltr" class="tabular-nums">{{ App\Support\Money::format($entry->amount) }}</span>
@endif
