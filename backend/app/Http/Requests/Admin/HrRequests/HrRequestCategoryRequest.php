<?php

namespace App\Http\Requests\Admin\HrRequests;

use App\Enums\HrRequestCategoryType;
use App\Enums\HrRequestFieldType;
use App\Enums\HrRequestPriority;
use App\Models\HrRequestCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HrRequestCategoryRequest extends FormRequest
{
    public function category(): ?HrRequestCategory
    {
        $category = $this->route('hr_request_category');

        return $category instanceof HrRequestCategory ? $category : null;
    }

    public function authorize(): bool
    {
        $category = $this->category();

        return $category ? $this->user()->can('update', $category) : $this->user()->can('create', HrRequestCategory::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? mb_strtoupper(trim($this->input('code'))) : $this->input('code'),
            'requires_approval' => $this->boolean('requires_approval'),
            'requires_attachment' => $this->boolean('requires_attachment'),
            'is_active' => $this->boolean('is_active'),
            'fields' => array_values(array_filter((array) $this->input('fields', []), fn ($field): bool => is_array($field) && filled($field['key'] ?? null))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $adminRule = Rule::exists('users', 'id')->where('account_type', 'admin')->whereNull('deleted_at');

        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('hr_request_categories', 'code')->ignore($this->category())],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'description_ar' => ['nullable', 'string', 'max:1000'],
            'description_en' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::enum(HrRequestCategoryType::class)],
            'hr_letter_template_id' => ['nullable', 'required_if:type,letter', 'integer', Rule::exists('hr_letter_templates', 'id')],
            'sla_hours' => ['nullable', 'integer', 'between:1,2160'],
            'default_priority' => ['required', Rule::enum(HrRequestPriority::class)],
            'default_assignee_id' => ['nullable', 'integer', $adminRule],
            'escalation_user_id' => ['nullable', 'integer', $adminRule],
            'requires_approval' => ['boolean'],
            'requires_attachment' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
            'fields' => ['array', 'max:10'],
            'fields.*.key' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'fields.*.label_ar' => ['required', 'string', 'max:150'],
            'fields.*.label_en' => ['required', 'string', 'max:150'],
            'fields.*.type' => ['required', Rule::enum(HrRequestFieldType::class)],
            'fields.*.options' => ['nullable', 'required_if:fields.*.type,select', 'string', 'max:1000'],
            'fields.*.is_required' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function categoryData(): array
    {
        $data = $this->safe()->except('fields');
        $data['sort_order'] ??= 0;

        if (($data['type'] ?? null) !== HrRequestCategoryType::Letter->value) {
            $data['hr_letter_template_id'] = null;
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('hr_requests.category_fields');
    }
}
