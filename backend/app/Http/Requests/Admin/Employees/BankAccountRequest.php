<?php

namespace App\Http\Requests\Admin\Employees;

use App\Models\Employee;
use App\Support\SensitiveValue;
use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
{
    public function employee(): Employee
    {
        return $this->route('employee');
    }

    public function authorize(): bool
    {
        return $this->user()->can('updateFinancial', $this->employee());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'iban' => SensitiveValue::normalise($this->input('iban')),
            'swift_code' => SensitiveValue::normalise($this->input('swift_code')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:150'],
            'account_holder' => ['required', 'string', 'max:150'],
            'account_number' => ['nullable', 'required_without:iban', 'string', 'max:34', 'regex:/^[0-9A-Za-z\s-]+$/'],
            'iban' => ['nullable', 'string', 'between:15,34', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]+$/'],
            'swift_code' => ['nullable', 'string', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('employees.fields');
    }
}
