<?php

namespace App\Http\Requests\Admin\Employees;

use App\Enums\ContractType;
use App\Models\Employee;
use App\Models\EmployeeContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add, correct or renew a contract (with an optional signed copy).
 */
class ContractRequest extends FormRequest
{
    public function employee(): Employee
    {
        return $this->route('employee');
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->employee());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reference' => ['nullable', 'string', 'max:50'],
            'contract_type' => ['required', Rule::enum(ContractType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => [
                Rule::requiredIf(fn (): bool => ContractType::tryFrom((string) $this->input('contract_type'))?->requiresEndDate() ?? false),
                'nullable', 'date', 'after:start_date',
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => ['nullable', 'file', ...DocumentRequest::fileRules()],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $renewed = $this->routeIs('admin.employees.contracts.renew') ? $this->route('contract') : null;

                if ($renewed instanceof EmployeeContract && $renewed->end_date !== null && filled($this->input('start_date'))
                    && strtotime((string) $this->input('start_date')) <= $renewed->end_date->getTimestamp()) {
                    $validator->errors()->add('start_date', __('employees.validation.renewal_overlaps', ['date' => $renewed->end_date->toDateString()]));
                }
            },
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
