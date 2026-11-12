<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A progress update: a percentage, or (KPIs) the actual value. Access is decided by the controller.
 */
class GoalProgressRequest extends FormRequest
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
            'progress' => ['nullable', 'required_without:current_value', 'decimal:0,2', 'between:0,100'],
            'current_value' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
