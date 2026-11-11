<?php

namespace App\Http\Requests\Admin\Leave;

use App\Enums\LeaveTransactionType;
use App\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A manual ledger entry: adjustment (signed) or encashment (days paid out).
 */
class BalanceTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leave_balances.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'leave_type_id' => ['required', 'integer', Rule::exists(LeaveType::class, 'id')->where('tracks_balance', true)],
            'leave_year' => ['required', 'integer', 'between:2000,2100'],
            'type' => ['required', Rule::in(array_map(fn (LeaveTransactionType $type): string => $type->value, LeaveTransactionType::manual()))],
            'amount' => ['required', 'decimal:0,2', 'not_in:0', 'between:-366,366'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('leave.fields');
    }
}
