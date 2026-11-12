{{-- A DECIMAL amount, LTR, with the currency. Variables: $amount, optional $currency. --}}
<span dir="ltr" class="tabular-nums whitespace-nowrap">{{ App\Support\Formatter::money($amount) ?: '—' }}@if (! empty($currency)) <span class="text-text-tertiary">{{ $currency }}</span>@endif</span>
