<?php

namespace App\Http\Requests\Loans;

use App\Enums\LoanType;
use Illuminate\Validation\Rule;

/**
 * Shape of a loan / advance request (portal, API, admin on behalf). Limits
 * are checked by App\Services\Payroll\LoanEligibility.
 */
trait LoanRules
{
    /**
     * @return array<string, mixed>
     */
    protected function loanRules(): array
    {
        return [
            'type' => ['required', Rule::enum(LoanType::class)],
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'instalments' => ['required', 'integer', 'between:1,120'],
            'start_month' => ['required', 'date_format:Y-m'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array{type: string, amount: string, instalments: int, start_month: string, reason: ?string}
     */
    public function loanData(): array
    {
        return [
            'type' => $this->validated('type'),
            'amount' => (string) $this->validated('amount'),
            'instalments' => (int) $this->validated('instalments'),
            'start_month' => $this->validated('start_month'),
            'reason' => $this->validated('reason'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll.loans.fields');
    }
}
