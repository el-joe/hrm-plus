<?php

namespace App\Http\Requests\Admin\Payroll;

use App\Enums\PayComponentKind;
use App\Models\PayEntry;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The same entry for many employees (one entry each).
 */
class BulkPayEntryRequest extends FormRequest
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
            ...collect($this->entryRules())->except(['attachments', 'attachments.*'])->all(),
            'employee_ids' => ['required', 'array', 'min:1', 'max:2000'],
            'employee_ids.*' => ['integer', 'distinct'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
