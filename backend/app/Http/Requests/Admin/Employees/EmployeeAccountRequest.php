<?php

namespace App\Http\Requests\Admin\Employees;

use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create the employee's login account (an invitation to set a password is emailed).
 */
class EmployeeAccountRequest extends FormRequest
{
    public function employee(): Employee
    {
        return $this->route('employee');
    }

    public function authorize(): bool
    {
        return $this->user()->can('manage', $this->employee());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'locale' => ['nullable', Rule::in(array_keys(config('app.supported_locales')))],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->employee()->user()->exists()) {
                    $validator->errors()->add('account_email', __('employees.account.already_linked'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['account_email' => __('employees.account.email')];
    }
}
