<?php

namespace App\Http\Requests\Admin\Lifecycle;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Set an onboarding task / handover item status. Authorised by the controller.
 */
class TaskStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function status(): TaskStatus
    {
        return TaskStatus::from($this->validated('status'));
    }
}
