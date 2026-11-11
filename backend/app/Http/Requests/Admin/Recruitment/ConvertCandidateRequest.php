<?php

namespace App\Http\Requests\Admin\Recruitment;

use App\Http\Requests\Admin\Employees\ValidatesPlacement;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\Recruitment\SalaryVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Hire form: the employee's names, contact, hire date and placement
 * (validated exactly like the employee create form, including the position
 * headcount), the starting salary (only from admins who may see salaries)
 * and, optionally, a login account.
 */
class ConvertCandidateRequest extends FormRequest
{
    use ValidatesPlacement;

    public function application(): JobApplication
    {
        return $this->route('application');
    }

    public function authorize(): bool
    {
        return $this->user()->can('convert', $this->application());
    }

    protected function prepareForValidation(): void
    {
        foreach (['email', 'account_email'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => mb_strtolower(trim($this->input($field)))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'date_of_birth' => ['nullable', 'date', 'before:-14 years'],
            'hire_date' => ['required', 'date', 'after:-1 year', 'before_or_equal:+1 year'],
            'probation_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            ...$this->placementRules(null),
            'basic_salary' => app(SalaryVisibility::class)->allows($this->user()) && $this->user()->can('employee_financials.update')
                ? ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99']
                : ['exclude'],
            'create_account' => ['sometimes', 'boolean'],
            'account_email' => ['nullable', Rule::requiredIf($this->boolean('create_account')), 'email', 'max:255', Rule::unique(User::class, 'email')->withoutTrashed()],
            'locale' => ['nullable', Rule::in(['ar', 'en'])],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validatePlacement($validator, null)];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [...$this->validated(), 'create_account' => $this->boolean('create_account')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...trans('employees.fields'), ...trans('recruitment.fields')];
    }
}
