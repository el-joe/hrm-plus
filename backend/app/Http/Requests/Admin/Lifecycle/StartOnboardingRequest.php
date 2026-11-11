<?php

namespace App\Http\Requests\Admin\Lifecycle;

use App\Models\Employee;
use App\Models\OnboardingTemplate;
use App\Rules\ExistsInScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('onboarding.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', new ExistsInScope(Employee::class)],
            'onboarding_template_id' => ['nullable', 'integer', Rule::exists(OnboardingTemplate::class, 'id')->where('is_active', true)],
            'start_date' => ['required', 'date', 'after:-1 year', 'before:+1 year'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('onboarding.fields');
    }
}
