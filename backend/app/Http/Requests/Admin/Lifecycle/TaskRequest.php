<?php

namespace App\Http\Requests\Admin\Lifecycle;

use App\Enums\TaskAssignee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A task row: of an onboarding template (due_offset_days) or added to a
 * running onboarding (due_date). Authorisation is done by the controller
 * (template: onboarding.manage; onboarding: update policy).
 */
class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title_ar' => ['required', 'string', 'max:200'],
            'title_en' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'assignee' => ['required', Rule::enum(TaskAssignee::class)],
            'due_offset_days' => ['sometimes', 'required', 'integer', 'between:-60,365'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'between:0,9999'],
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
