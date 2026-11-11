<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Enums\PayComponentKind;
use App\Enums\PayEntryTarget;
use App\Models\PayEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One allowance / deduction entry; the kind comes from the route's controller.
 */
class PayEntryRequest extends FormRequest
{
    use PayEntryRules;

    public function kind(): PayComponentKind
    {
        return $this->route()->getController()->kind();
    }

    public function authorize(): bool
    {
        return $this->user()->can('create', [PayEntry::class, $this->kind()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->entryRules(),
            'target_type' => ['required', Rule::enum(PayEntryTarget::class)],
            'employee_id' => ['nullable', 'integer'],
            'employee_group_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * Entry data with target_id taken from the select of the chosen target type.
     *
     * @return array<string, mixed>
     */
    public function entryData(): array
    {
        $data = collect($this->validated())->except(['attachments', 'employee_id', 'employee_group_id', 'department_id', 'branch_id'])->all();
        $data['target_id'] = match (PayEntryTarget::from($this->validated('target_type'))) {
            PayEntryTarget::Employee => $this->validated('employee_id'),
            PayEntryTarget::EmployeeGroup => $this->validated('employee_group_id'),
            PayEntryTarget::Department => $this->validated('department_id'),
            PayEntryTarget::Branch => $this->validated('branch_id'),
            PayEntryTarget::Company => null,
        };

        return $data;
    }

    public function after(): array
    {
        return [function ($validator): void {
            if ($this->input('target_type') === PayEntryTarget::Company->value && $this->user()->hasDataRestrictions()) {
                $validator->errors()->add('target_type', __('payroll.validation.company_scope_restricted'));
            }
        }];
    }
}
