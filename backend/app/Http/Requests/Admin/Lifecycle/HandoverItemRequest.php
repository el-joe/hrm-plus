<?php

namespace App\Http\Requests\Admin\Lifecycle;

use App\Enums\HandoverCategory;
use App\Enums\TaskAssignee;
use App\Models\OffboardingCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HandoverItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('offboarding');

        return $case instanceof OffboardingCase && $this->user()->can('update', $case);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(HandoverCategory::class)],
            'assignee' => ['required', Rule::enum(TaskAssignee::class)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
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
