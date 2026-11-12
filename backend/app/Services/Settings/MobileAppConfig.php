<?php

namespace App\Services\Settings;

use App\Models\Company;
use App\Services\Auth\PasswordResetCodes;

/**
 * Public configuration of the employee mobile app (GET /api/v1/app/config), read on the splash
 * screen before sign-in. Only non-sensitive values: company identity, languages, display
 * formats, feature switches, password rules and app versions (settings group mobile_app).
 *
 * The face verification mode actually applied to an employee comes from their attendance policy
 * (GET /api/v1/me/attendance/today → face.mode); here it is only the company default.
 */
class MobileAppConfig
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $company = Company::current();

        return [
            'company' => [
                'name' => $company->name,
                'name_translations' => ['ar' => $company->name_ar, 'en' => $company->name_en],
                'logo_url' => $company->logoUrl(),
            ],
            'locales' => collect(config('app.supported_locales'))->map(fn (string $direction, string $code): array => [
                'code' => $code,
                'dir' => $direction,
                'name' => __('app.locales.'.$code, [], $code),
            ])->values()->all(),
            'default_locale' => (string) setting('localisation.default_locale'),
            'localisation' => [
                'timezone' => (string) setting('localisation.timezone'),
                'date_format' => (string) setting('localisation.date_format'),
                'time_format' => (string) setting('localisation.time_format'),
                'use_arabic_digits' => (bool) setting('localisation.use_arabic_digits'),
                'currency_code' => (string) setting('localisation.currency_code'),
                'currency_decimals' => (int) setting('localisation.currency_decimals'),
                'first_day_of_week' => (string) setting('localisation.first_day_of_week'),
            ],
            'features' => [
                'face_verification_required_by_default' => (bool) setting('attendance.face_verification_required'),
                'face_provider' => (string) setting('face_verification.provider'),
                'face_provider_simulated' => setting('face_verification.provider') === 'simulated',
                'push_enabled' => (bool) setting('notifications.push_enabled'),
                'push_provider' => (string) setting('push.provider'),
                'password_reset_otp' => true,
                'loan_requests' => (bool) setting('loans.employee_requests_enabled'),
                'hr_requests' => (bool) setting('hr_requests.employee_requests_enabled'),
                'training_requests' => (bool) setting('training.self_requests_enabled'),
                'employee_goals' => (bool) setting('performance.employees_can_add_goals'),
            ],
            'auth' => [
                'otp_length' => PasswordResetCodes::LENGTH,
                'otp_ttl_seconds' => (int) setting('security.password_reset_code_minutes') * 60,
                'otp_resend_seconds' => PasswordResetCodes::RESEND_SECONDS,
                'otp_max_attempts' => (int) setting('security.password_reset_code_attempts'),
                'password_min_length' => (int) setting('security.password_min_length'),
                'password_require_mixed_case' => (bool) setting('security.password_require_mixed_case'),
                'password_require_symbols' => (bool) setting('security.password_require_symbols'),
                'token_lifetime_minutes' => (int) config('sanctum.expiration'),
            ],
            'app' => [
                'min_version' => (string) setting('mobile_app.min_version'),
                'latest_version' => (string) setting('mobile_app.latest_version'),
                'android_store_url' => setting('mobile_app.android_store_url') ?: null,
                'ios_store_url' => setting('mobile_app.ios_store_url') ?: null,
            ],
            'server_time' => now()->utc()->toIso8601ZuluString(),
        ];
    }
}
