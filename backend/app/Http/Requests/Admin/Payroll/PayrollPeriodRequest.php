<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Enums\PayrollPeriodType;
use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A new payroll period: a month (YYYY-MM, dates from setting payroll.period_start_day)
 * or custom dates with a code and names. Edits change only the pay date, cut-off and notes.
 */
class PayrollPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage', PayrollPeriod::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->route('payroll_period') instanceof PayrollPeriod) {
            return [
                'pay_date' => ['required', 'date'],
                'cutoff_date' => ['nullable', 'date'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ];
        }

        $custom = $this->input('type') === PayrollPeriodType::Custom->value;

        return [
            'type' => ['required', Rule::enum(PayrollPeriodType::class)],
            'month' => [$custom ? 'nullable' : 'required', 'date_format:Y-m'],
            'code' => [$custom ? 'required' : 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'],
            'name_ar' => [$custom ? 'required' : 'nullable', 'string', 'max:150'],
            'name_en' => [$custom ? 'required' : 'nullable', 'string', 'max:150'],
            'start_date' => [$custom ? 'required' : 'nullable', 'date'],
            'end_date' => [$custom ? 'required' : 'nullable', 'date', 'after_or_equal:start_date'],
            'pay_date' => [$custom ? 'required' : 'nullable', 'date'],
            'cutoff_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll_runs.periods.fields');
    }
}
