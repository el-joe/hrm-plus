<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ChangePasswordRequest;
use App\Http\Requests\Api\V1\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\ResetPasswordWithCodeRequest;
use App\Http\Requests\Api\V1\VerifyResetCodeRequest;
use App\Services\Auth\PasswordResetCodes;
use App\Support\ApiResponse;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Password flows of the mobile app: forgot password with an e-mailed one-time code
 * (forgot → verify-otp → reset, all public and rate limited) and changing the password
 * while signed in.
 */
class PasswordController extends Controller
{
    public function __construct(private readonly PasswordResetCodes $codes) {}

    /**
     * Ask for a reset code. Always answers the same way, whether or not the address has an account.
     *
     * @unauthenticated
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->codes->send($request->validated('email'));

        return ApiResponse::success([
            'expires_in' => $this->codes->minutes() * 60,
            'resend_after' => PasswordResetCodes::RESEND_SECONDS,
            'code_length' => PasswordResetCodes::LENGTH,
        ], __('passwords.code.sent'));
    }

    /**
     * Check a reset code without using it (OTP screen). Wrong codes count towards the attempt limit.
     *
     * @unauthenticated
     */
    public function verify(VerifyResetCodeRequest $request): JsonResponse
    {
        $expiresAt = $this->codes->verify($request->validated('email'), $request->validated('code'));

        if ($expiresAt === null) {
            throw ValidationException::withMessages(['code' => __('passwords.code.invalid')]);
        }

        return ApiResponse::success(['valid' => true, 'expires_at' => $expiresAt->toIso8601String()], __('passwords.code.valid'));
    }

    /**
     * Set a new password with a valid reset code. Every token of the account is revoked.
     *
     * @unauthenticated
     */
    public function reset(ResetPasswordWithCodeRequest $request, ChangePassword $change): JsonResponse
    {
        $user = $this->codes->consume($request->validated('email'), $request->validated('code'));

        if ($user === null) {
            throw ValidationException::withMessages(['code' => __('passwords.code.invalid')]);
        }

        $change->handle($user, $request->validated('password'), reason: 'reset');
        event(new PasswordReset($user));

        return ApiResponse::success(message: __('passwords.reset'));
    }

    /**
     * Change the password of the signed-in account. Other app tokens and web sessions are ended;
     * the token of this request stays valid.
     */
    public function change(ChangePasswordRequest $request, ChangePassword $change): JsonResponse
    {
        $change->handle($request->user(), $request->validated('password'), keepTokenId: (int) $request->user()->currentAccessToken()->id);

        return ApiResponse::success(message: __('passwords.changed'));
    }
}
