<?php

namespace App\Http\Requests\Loans;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An employee's own loan / advance request (portal and mobile API).
 */
class OwnLoanRequest extends FormRequest
{
    use LoanRules;

    public function authorize(): bool
    {
        return $this->user()?->employee_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->loanRules();
    }
}
