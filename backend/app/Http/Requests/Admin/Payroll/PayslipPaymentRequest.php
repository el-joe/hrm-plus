<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Enums\PaymentMethod;
use App\Models\Payslip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A payment against one payslip, or "mark paid" for several (payslip_ids).
 */
class PayslipPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $payslip = $this->route('payslip');

        return $payslip instanceof Payslip ? $this->user()->can('pay', $payslip) : $this->user()->can('pay', Payslip::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $single = $this->route('payslip') instanceof Payslip;

        return [
            'amount' => [$single ? 'required' : 'prohibited', 'decimal:0,2', 'gt:0'],
            'payslip_ids' => [$single ? 'prohibited' : 'required', 'array', 'min:1', 'max:1000'],
            'payslip_ids.*' => ['integer'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll_runs.payslips.fields');
    }
}
