<?php

namespace App\Http\Requests\Admin\Employees;

use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Rules\ExistsInScope;
use App\Services\Employees\CustomFieldValues;
use App\Support\SensitiveValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create and edit an employee. On create it also takes the initial placement
 * and (with employee_financials.update) the starting salary; on edit the
 * placement is not accepted — it changes through dated history entries.
 * National id / passport number are only accepted from admins with
 * employee_sensitive.update; for anyone else they are dropped.
 */
class EmployeeRequest extends FormRequest
{
    use ValidatesPlacement;

    public function employee(): ?Employee
    {
        $employee = $this->route('employee');

        return $employee instanceof Employee ? $employee : null;
    }

    public function authorize(): bool
    {
        $employee = $this->employee();

        return $employee ? $this->user()->can('update', $employee) : $this->user()->can('create', Employee::class);
    }

    public function canEditSensitive(): bool
    {
        $employee = $this->employee();

        return $employee ? $this->user()->can('updateSensitive', $employee) : $this->user()->can('employee_sensitive.update');
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['employee_number', 'email', 'personal_email', 'nationality', 'country_code'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $merge[$field] = in_array($field, ['nationality', 'country_code', 'employee_number'], true) ? mb_strtoupper($value) : mb_strtolower($value);
            }
        }

        foreach (['national_id', 'passport_number'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = SensitiveValue::normalise($this->input($field));
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $employee = $this->employee();
        $sensitive = $this->canEditSensitive();

        $rules = [
            'employee_number' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/', Rule::unique('employees', 'employee_number')->ignore($employee)],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'date_of_birth' => ['nullable', 'date', 'before:-14 years', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'marital_status' => ['nullable', Rule::enum(MaritalStatus::class)],
            'nationality' => ['nullable', 'string', 'size:2', 'alpha'],
            'national_id' => $sensitive ? ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/', $this->uniqueHash('national_id_hash')] : ['exclude'],
            'passport_number' => $sensitive ? ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'] : ['exclude'],
            'passport_expiry_date' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($employee)],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'alternate_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'probation_end_date' => ['nullable', 'date'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('employees.photo_max_kb')],
            'remove_photo' => ['sometimes', 'boolean'],
            'emergency_contacts' => ['sometimes', 'array', 'max:3'],
            'emergency_contacts.*.name' => ['nullable', 'string', 'max:150'],
            'emergency_contacts.*.relationship' => ['required_with:emergency_contacts.*.name', 'nullable', 'string', 'max:50'],
            'emergency_contacts.*.phone' => ['required_with:emergency_contacts.*.name', 'nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'emergency_contacts.*.alternate_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'groups' => ['sometimes', 'array'],
            'groups.*' => ['integer', new ExistsInScope(EmployeeGroup::class, activeOnly: false)],
            ...app(CustomFieldValues::class)->rules(),
        ];

        if ($employee === null) {
            $rules += [
                ...$this->placementRules(null),
                'hire_date' => ['required', 'date', 'after:1950-01-01', 'before_or_equal:+1 year'],
                'basic_salary' => $this->user()->can('employee_financials.update')
                    ? ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99']
                    : ['exclude'],
            ];
        }

        return $rules;
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->employee() === null) {
                    $this->validatePlacement($validator, null);
                }

                if (filled($this->input('probation_end_date')) && filled($this->input('hire_date'))
                    && strtotime((string) $this->input('probation_end_date')) < strtotime((string) $this->input('hire_date'))) {
                    $validator->errors()->add('probation_end_date', __('employees.validation.probation_before_hire'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...trans('employees.fields'),
            ...app(CustomFieldValues::class)->attributes(),
            'emergency_contacts.*.name' => __('employees.fields.contact_name'),
            'emergency_contacts.*.relationship' => __('employees.fields.relationship'),
            'emergency_contacts.*.phone' => __('employees.fields.phone'),
        ];
    }

    private function uniqueHash(string $column): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($column): void {
            $exists = Employee::query()->withoutGlobalScopes()
                ->where($column, SensitiveValue::hash((string) $value))
                ->when($this->employee(), fn ($query, Employee $employee) => $query->whereKeyNot($employee->getKey()))
                ->exists();

            if ($exists) {
                $fail(__('validation.unique', ['attribute' => __('employees.fields.'.$attribute)]));
            }
        };
    }
}
