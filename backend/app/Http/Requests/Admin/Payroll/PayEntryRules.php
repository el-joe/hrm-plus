<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Enums\PayEntryCalculation;
use App\Enums\PayEntryRecurrence;
use App\Models\SalaryComponent;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Shape rules shared by the single, bulk and on-the-profile entry forms.
 * Business rules (component kind, instalments, reason, dates) live in
 * App\Actions\Payroll\ManagePayEntry.
 */
trait PayEntryRules
{
    /**
     * @return array<string, mixed>
     */
    protected function entryRules(): array
    {
        $maxSize = (int) setting('documents.max_upload_size_kb');
        $extensions = implode(',', (array) setting('documents.allowed_extensions'));

        return [
            'salary_component_id' => ['required', 'integer', Rule::exists(SalaryComponent::class, 'id')],
            'calculation' => ['nullable', Rule::enum(PayEntryCalculation::class)],
            'amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'rate' => ['nullable', 'decimal:0,4', 'min:0', 'max:1000'],
            'base_component_id' => ['nullable', 'integer', Rule::exists(SalaryComponent::class, 'id')],
            'recurrence' => ['required', Rule::enum(PayEntryRecurrence::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'total_amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'instalment_count' => ['nullable', 'integer', 'between:1,120'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_insurable' => ['nullable', 'boolean'],
            'prorate' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:'.$maxSize, 'mimes:'.$extensions, 'extensions:'.$extensions],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function entryData(): array
    {
        return collect($this->validated())->except(['attachments', 'employee_ids', 'note'])->all();
    }

    /**
     * @return list<UploadedFile>
     */
    public function attachmentFiles(): array
    {
        return array_values($this->file('attachments') ?? []);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('payroll.fields');
    }
}
