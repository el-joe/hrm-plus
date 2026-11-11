<?php

namespace App\Http\Requests\Admin\Employees;

use App\Enums\DocumentCategory;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Upload (file required) or edit (no file) an employee document. Allowed
 * types and size come from the documents settings.
 */
class DocumentRequest extends FormRequest
{
    public function employee(): Employee
    {
        return $this->route('employee');
    }

    public function authorize(): bool
    {
        return $this->route('document') instanceof EmployeeDocument
            ? $this->user()->can('updateDocuments', $this->employee())
            : $this->user()->can('createDocuments', $this->employee());
    }

    /**
     * Upload rules from the documents settings (extension and real content type).
     *
     * @return list<string>
     */
    public static function fileRules(): array
    {
        return [
            'mimes:'.implode(',', (array) setting('documents.allowed_extensions')),
            'extensions:'.implode(',', (array) setting('documents.allowed_extensions')),
            'max:'.(int) setting('documents.max_upload_size_kb'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = ! $this->route('document') instanceof EmployeeDocument;

        return [
            'category' => ['required', Rule::enum(DocumentCategory::class)],
            'title' => ['required', 'string', 'max:150'],
            'issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'visible_to_employee' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'file' => $creating ? ['required', 'file', ...self::fileRules()] : ['prohibited'],
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
