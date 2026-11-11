<?php

namespace App\Http\Requests\Admin\Employees;

use App\Enums\CustomFieldType;
use App\Models\EmployeeCustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Definition of an employee custom field. The type is fixed once values
 * exist (changing it would orphan them). Select options: one per line,
 * "value|Arabic label|English label".
 */
class CustomFieldRequest extends FormRequest
{
    public function field(): ?EmployeeCustomField
    {
        $field = $this->route('custom_field');

        return $field instanceof EmployeeCustomField ? $field : null;
    }

    public function authorize(): bool
    {
        return $this->user()->can('employees.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtolower(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $field = $this->field();
        $locked = $field?->values()->exists();

        return [
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('employee_custom_fields', 'code')->ignore($field)],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'type' => $locked ? ['required', Rule::in([$field->type->value])] : ['required', Rule::enum(CustomFieldType::class)],
            'options' => [Rule::requiredIf($this->input('type') === CustomFieldType::Select->value), 'nullable', 'string', 'max:5000'],
            'is_required' => ['sometimes', 'boolean'],
            'visible_to_employee' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Options parsed from the textarea.
     *
     * @return list<array{value: string, label_ar: string, label_en: string}>
     */
    public function options(): array
    {
        if ($this->input('type') !== CustomFieldType::Select->value) {
            return [];
        }

        return collect(preg_split('/\R/', (string) $this->input('options')))
            ->map(fn (string $line): array => array_map('trim', explode('|', $line)))
            ->filter(fn (array $parts): bool => $parts[0] !== '')
            ->map(fn (array $parts): array => [
                'value' => mb_substr($parts[0], 0, 60),
                'label_ar' => mb_substr($parts[1] ?? $parts[0], 0, 150),
                'label_en' => mb_substr($parts[2] ?? $parts[1] ?? $parts[0], 0, 150),
            ])
            ->unique('value')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldData(): array
    {
        $data = $this->safe()->except(['options']);

        foreach (['is_required', 'visible_to_employee', 'is_active'] as $boolean) {
            $data[$boolean] = $this->boolean($boolean);
        }

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('employees.custom_fields.fields');
    }
}
