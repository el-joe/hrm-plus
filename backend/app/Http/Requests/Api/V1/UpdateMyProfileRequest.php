<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/v1/me/profile — what the employee may change directly: their language.
 * Protected profile data is changed through a profile change request (HR requests).
 */
class UpdateMyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(array_keys(config('app.supported_locales')))],
        ];
    }
}
