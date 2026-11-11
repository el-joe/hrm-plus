<?php

/*
|--------------------------------------------------------------------------
| System settings registry
|--------------------------------------------------------------------------
|
| Every business rule that the client may change lives here as a typed
| setting with a default. Values overridden by an admin are stored in the
| `settings` table; everything else falls back to the default below.
|
| Read with `setting('group.name')` or App\Services\Settings\SettingsService.
| Types: string | integer | boolean | decimal (string, never float) | list.
| `rules` are Laravel validation rules applied on the admin settings screen.
| `options` (optional) limits a value, or each item of a list, to that set.
|
| Later modules add their own keys to the matching group; do not hard-code
| a rule in a controller, service or JavaScript file.
|
| The company identity (names, legal details, logo) is not a setting: it is
| the company profile (App\Models\Company, /admin/company).
|
*/

return [

    'localisation' => [
        'default_locale' => ['type' => 'string', 'default' => 'ar', 'rules' => ['required'], 'options' => ['ar', 'en']],
        'timezone' => ['type' => 'string', 'default' => 'UTC', 'rules' => ['required', 'timezone']],
        'date_format' => ['type' => 'string', 'default' => 'Y-m-d', 'rules' => ['required'], 'options' => ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y']],
        'time_format' => ['type' => 'string', 'default' => 'H:i', 'rules' => ['required'], 'options' => ['H:i', 'h:i A']],
        'currency_code' => ['type' => 'string', 'default' => 'USD', 'rules' => ['required', 'string', 'size:3']],
        'currency_decimals' => ['type' => 'integer', 'default' => 2, 'rules' => ['required', 'integer', 'between:0,4']],
        'use_arabic_digits' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
        'first_day_of_week' => ['type' => 'string', 'default' => 'sunday', 'rules' => ['required'], 'options' => ['saturday', 'sunday', 'monday']],
    ],

    'attendance' => [
        'working_days' => [
            'type' => 'list', 'default' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday'], 'rules' => ['array'],
            'options' => ['saturday', 'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ],
        'late_grace_minutes' => ['type' => 'integer', 'default' => 10, 'rules' => ['required', 'integer', 'between:0,240']],
        'early_leave_grace_minutes' => ['type' => 'integer', 'default' => 10, 'rules' => ['required', 'integer', 'between:0,240']],
        'overtime_minimum_minutes' => ['type' => 'integer', 'default' => 30, 'rules' => ['required', 'integer', 'between:0,480']],
        'geofence_default_radius_meters' => ['type' => 'integer', 'default' => 150, 'rules' => ['required', 'integer', 'between:10,5000']],
        'gps_max_accuracy_meters' => ['type' => 'integer', 'default' => 50, 'rules' => ['required', 'integer', 'between:5,1000']],
        // Default of new policy versions: optional until a real face provider is configured (blocker B4).
        'face_verification_required' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
        'face_verification_max_retries' => ['type' => 'integer', 'default' => 3, 'rules' => ['required', 'integer', 'between:1,10']],
        'manual_entry_requires_approval' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
        // Phase 5b: defaults of the mobile rules of new attendance-policy versions, and device monitoring.
        'gps_accuracy_tolerance_meters' => ['type' => 'integer', 'default' => 20, 'rules' => ['required', 'integer', 'between:0,500']],
        'gps_max_time_drift_seconds' => ['type' => 'integer', 'default' => 300, 'rules' => ['required', 'integer', 'between:30,86400']],
        'gps_repeated_attempts_threshold' => ['type' => 'integer', 'default' => 3, 'rules' => ['required', 'integer', 'between:1,20']],
        'device_stale_after_hours' => ['type' => 'integer', 'default' => 24, 'rules' => ['required', 'integer', 'between:1,720']],
    ],

    // Face verification (Phase 5b): provider, consent text version and biometric retention.
    'face_verification' => [
        'provider' => ['type' => 'string', 'default' => 'simulated', 'rules' => ['required', 'string'], 'options' => ['simulated']],
        'consent_version' => ['type' => 'string', 'default' => '1.0', 'rules' => ['required', 'string', 'max:20']],
        'enrolment_max_age_days' => ['type' => 'integer', 'default' => 730, 'rules' => ['required', 'integer', 'between:30,3650']],
        'verification_log_retention_days' => ['type' => 'integer', 'default' => 365, 'rules' => ['required', 'integer', 'between:30,3650']],
        'store_enrolment_image' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
    ],

    'payroll' => [
        'period_start_day' => ['type' => 'integer', 'default' => 1, 'rules' => ['required', 'integer', 'between:1,28']],
        'working_days_per_month' => ['type' => 'integer', 'default' => 30, 'rules' => ['required', 'integer', 'between:1,31']],
        'working_hours_per_day' => ['type' => 'decimal', 'default' => '8.00', 'rules' => ['required', 'decimal:0,2', 'gt:0', 'lte:24']],
        'overtime_rate_multiplier' => ['type' => 'decimal', 'default' => '1.50', 'rules' => ['required', 'decimal:0,2', 'gte:1']],
        'rounding_precision' => ['type' => 'integer', 'default' => 2, 'rules' => ['required', 'integer', 'between:0,4']],
    ],

    'leave' => [
        'year_start_month' => ['type' => 'integer', 'default' => 1, 'rules' => ['required', 'integer', 'between:1,12']],
        'allow_half_day' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'allow_negative_balance' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
        'count_weekends_as_leave' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
        // Phase 6. How many days back employees may request leave of types that allow past dates.
        'max_past_days' => ['type' => 'integer', 'default' => 30, 'rules' => ['required', 'integer', 'between:0,365']],
        // Approved leave that has not started may be cancelled by the employee without approval;
        // once started (or when this is off) a cancellation request goes through approval.
        'cancel_before_start_directly' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        // Leave calendar: highlight a day when at least this many employees of one department are away.
        'calendar_conflict_threshold' => ['type' => 'integer', 'default' => 2, 'rules' => ['required', 'integer', 'between:2,50']],
    ],

    'notifications' => [
        'email_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'push_enabled' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
        'contract_expiry_reminder_days' => ['type' => 'integer', 'default' => 30, 'rules' => ['required', 'integer', 'between:1,365']],
        'document_expiry_reminder_days' => ['type' => 'integer', 'default' => 30, 'rules' => ['required', 'integer', 'between:1,365']],
    ],

    'documents' => [
        'max_upload_size_kb' => ['type' => 'integer', 'default' => 10240, 'rules' => ['required', 'integer', 'between:100,102400']],
        'allowed_extensions' => [
            'type' => 'list', 'default' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'], 'rules' => ['array'],
            'options' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
        ],
    ],

    // Pattern tokens: {prefix}, {number} (zero-padded sequence, required),
    // {year} (hire year), {branch} (branch code). Generated race-safely by
    // App\Services\Employees\EmployeeNumberGenerator; the sequence never
    // goes below next_sequence and skips numbers already taken.
    'employee_number' => [
        'pattern' => ['type' => 'string', 'default' => '{prefix}{number}', 'rules' => ['required', 'string', 'max:40', 'regex:/\{number\}/']],
        'prefix' => ['type' => 'string', 'default' => 'EMP-', 'rules' => ['nullable', 'string', 'max:10']],
        'padding' => ['type' => 'integer', 'default' => 5, 'rules' => ['required', 'integer', 'between:1,10']],
        'next_sequence' => ['type' => 'integer', 'default' => 1, 'rules' => ['required', 'integer', 'min:1']],
    ],

    'security' => [
        'password_min_length' => ['type' => 'integer', 'default' => 8, 'rules' => ['required', 'integer', 'between:8,64']],
    ],

    'approvals' => [
        'allow_self_approval' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
    ],

    // Recruitment (Phase 4). The public career page is on by default outside
    // production and must be switched on deliberately in production.
    'recruitment' => [
        'career_page_enabled' => ['type' => 'boolean', 'default' => env('APP_ENV', 'production') !== 'production', 'rules' => ['boolean']],
        'career_applications_per_hour' => ['type' => 'integer', 'default' => 5, 'rules' => ['required', 'integer', 'between:1,100']],
        'cv_max_size_kb' => ['type' => 'integer', 'default' => 5120, 'rules' => ['required', 'integer', 'between:100,20480']],
        'cv_allowed_extensions' => [
            'type' => 'list', 'default' => ['pdf', 'doc', 'docx'], 'rules' => ['array'],
            'options' => ['pdf', 'doc', 'docx', 'rtf', 'odt', 'txt'],
        ],
        'candidate_emails_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'interview_reminder_hours' => ['type' => 'integer', 'default' => 24, 'rules' => ['required', 'integer', 'between:1,168']],
        'offer_requires_approval' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'offer_validity_days' => ['type' => 'integer', 'default' => 7, 'rules' => ['required', 'integer', 'between:1,90']],
    ],

    // Onboarding and offboarding (Phase 4).
    'lifecycle' => [
        'auto_start_onboarding' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'contract_expiry_case_days' => ['type' => 'integer', 'default' => 30, 'rules' => ['required', 'integer', 'between:1,180']],
    ],

];
