<?php

namespace App\Http\Requests\Account;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * The signed-in user changes their own password (portal account settings and PUT /api/v1/auth/password).
 * The current password is checked against the authenticated account of whichever guard served the request.
 */
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! Hash::check($value, (string) $this->user()->password)) {
                    $fail(__('passwords.current_wrong'));
                }
            }],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => __('app.account.current_password'),
            'password' => __('app.account.new_password'),
        ];
    }
}
