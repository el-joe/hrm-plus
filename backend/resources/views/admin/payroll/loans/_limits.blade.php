{{-- One line of loan limits. Variables: $limit (LoanEligibility::limits()). Advances have no separate instalment cap. --}}
{{ $limit['max_instalment'] === null
    ? __('payroll.loans.limits_help_advance', ['amount' => App\Support\Money::format($limit['max_amount']), 'instalments' => $limit['max_instalments']])
    : __('payroll.loans.limits_help', ['amount' => App\Support\Money::format($limit['max_amount']), 'instalments' => $limit['max_instalments'], 'instalment' => App\Support\Money::format($limit['max_instalment'])]) }}
