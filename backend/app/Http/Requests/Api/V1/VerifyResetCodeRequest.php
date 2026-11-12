<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Auth\PasswordResetCodes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/password/verify-otp — checks a reset code (not consumed).
 */
class VerifyResetCodeRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'digits:'.PasswordResetCodes::LENGTH],
        ];
    }
}
