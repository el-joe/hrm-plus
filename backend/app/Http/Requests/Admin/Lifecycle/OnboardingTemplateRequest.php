<?php

namespace App\Http\Requests\Admin\Lifecycle;

use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\OnboardingTemplate;
use App\Models\Position;
use App\Rules\ExistsInScope;
use Illuminate\Foundation\Http\FormRequest;

class OnboardingTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('onboarding.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $template = $this->route('onboarding_template');
        $template = $template instanceof OnboardingTemplate ? $template : null;

        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['nullable', 'integer', new ExistsInScope(Department::class, keep: $template?->department_id)],
            'position_id' => ['nullable', 'integer', new ExistsInScope(Position::class, keep: $template?->position_id)],
            'employment_type_id' => ['nullable', 'integer', new ExistsInScope(EmploymentType::class, keep: $template?->employment_type_id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [...$this->validated(), 'is_active' => $this->boolean('is_active')];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('onboarding.fields');
    }
}
