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
| Types: string | integer | boolean | decimal (string, never float) | list |
| secret (encrypted at rest, never echoed back; an empty form value keeps it).
| 'form' => false keeps a key off the generic settings form (set by its own screen).
| `rules` are Laravel validation rules applied on the admin settings screen.
| `options` (optional) limits a value, or each item of a list, to that set.
| `item_rules` (optional, lists without options) validate each item of a free list (one per line on the form).
|
| Later modules add their own keys to the matching group; do not hard-code
| a rule in a controller, service or JavaScript file.
|
| The company identity (names, legal details, logo) is not a setting: it is
| the company profile (App\Models\Company, /admin/company).
|
*/

// A CSP source origin: http(s)://host[:port], optionally with a leading "*." wildcard subdomain, no path.
$cspOrigin = '/^https?:\/\/(\*\.)?[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*(?::\d{1,5})?$/';

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
        // Phase 7a. Country whose statutory rule tables (tax, social insurance) payroll applies (ISO 3166 alpha-2).
        // Empty: no statutory deduction is calculated until the client confirms the rules (blocker B7).
        'country_code' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'size:2', 'alpha']],
        // Phase 7b. Runs with more employees than this are calculated by a queued job (progress shown on the run).
        'sync_calculation_limit' => ['type' => 'integer', 'default' => 150, 'rules' => ['required', 'integer', 'between:1,5000']],
        // Earning component (code) used for leave encashment lines (days × day rate).
        'leave_encashment_component' => ['type' => 'string', 'default' => 'LEAVE_ENCASH', 'rules' => ['nullable', 'string', 'max:40']],
        // Show the current salary structure (lines and amounts) in the portal "My payroll" page.
        'portal_show_structure' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
    ],

    // Loans and salary advances (Phase 7a): eligibility and limits checked on every request.
    'loans' => [
        'employee_requests_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'min_service_months' => ['type' => 'integer', 'default' => 6, 'rules' => ['required', 'integer', 'between:0,120']],
        'max_active_loans' => ['type' => 'integer', 'default' => 1, 'rules' => ['required', 'integer', 'between:1,10']],
        // Largest loan as a multiple of the monthly basic salary.
        'max_loan_salary_multiple' => ['type' => 'decimal', 'default' => '3.00', 'rules' => ['required', 'decimal:0,2', 'gt:0', 'lte:100']],
        'max_loan_instalments' => ['type' => 'integer', 'default' => 24, 'rules' => ['required', 'integer', 'between:1,120']],
        // Largest advance as a percentage of the monthly basic salary.
        'max_advance_percent' => ['type' => 'decimal', 'default' => '50.00', 'rules' => ['required', 'decimal:0,2', 'gt:0', 'lte:100']],
        'max_advance_instalments' => ['type' => 'integer', 'default' => 1, 'rules' => ['required', 'integer', 'between:1,12']],
        // A monthly instalment may not exceed this percentage of the monthly basic salary.
        'max_instalment_percent' => ['type' => 'decimal', 'default' => '25.00', 'rules' => ['required', 'decimal:0,2', 'gt:0', 'lte:100']],
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

    // Notification channels and automation lead times (Phase 8b). Per-user choices are notification
    // preferences; the texts are notification templates (admin/notification-templates).
    'notifications' => [
        'email_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'push_enabled' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
        'contract_expiry_reminder_days' => ['type' => 'integer', 'default' => 30, 'rules' => ['required', 'integer', 'between:1,365']],
        'document_expiry_reminder_days' => ['type' => 'integer', 'default' => 30, 'rules' => ['required', 'integer', 'between:1,365']],
        // Managers and HR are told about birthdays this many days ahead (0 = only on the day); greetings on the day.
        'birthday_notice_days' => ['type' => 'integer', 'default' => 3, 'rules' => ['required', 'integer', 'between:0,30']],
        'birthday_greetings_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'work_anniversary_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        // Managers and HR are reminded this many days before a probation period ends.
        'probation_reminder_days' => ['type' => 'integer', 'default' => 14, 'rules' => ['required', 'integer', 'between:1,90']],
        // Employees are reminded of a check-in without a check-out this long after the scheduled end.
        'missing_checkout_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'missing_checkout_after_minutes' => ['type' => 'integer', 'default' => 120, 'rules' => ['required', 'integer', 'between:15,1440']],
        'late_arrival_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        // Unexplained absences (attendance issue): the employee and their direct manager.
        'absence_alerts_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        // Enrolled employees are reminded this many days before a training session starts.
        'training_reminder_days' => ['type' => 'integer', 'default' => 2, 'rules' => ['required', 'integer', 'between:1,30']],
        // Approvers receive a digest of requests waiting longer than this.
        'approvals_digest_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'approvals_reminder_hours' => ['type' => 'integer', 'default' => 24, 'rules' => ['required', 'integer', 'between:1,336']],
    ],

    // Outgoing e-mail (Phase 8b). Applied at runtime over config/mail.php; "log" writes messages to the
    // application log (blocker B6: SMTP credentials not supplied yet). The password is encrypted at rest
    // and never shown again.
    'mail' => [
        'mailer' => ['type' => 'string', 'default' => env('MAIL_MAILER', 'log') === 'smtp' ? 'smtp' : 'log', 'rules' => ['required'], 'options' => ['log', 'smtp']],
        'host' => ['type' => 'string', 'default' => (string) env('MAIL_HOST', ''), 'rules' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:255']],
        'port' => ['type' => 'integer', 'default' => (int) env('MAIL_PORT', 587), 'rules' => ['required', 'integer', 'between:1,65535']],
        'encryption' => ['type' => 'string', 'default' => 'tls', 'rules' => ['required'], 'options' => ['none', 'tls', 'ssl']],
        'username' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'string', 'max:255']],
        'password' => ['type' => 'secret', 'default' => null, 'rules' => ['nullable', 'string', 'max:255']],
        'from_address' => ['type' => 'string', 'default' => (string) env('MAIL_FROM_ADDRESS', 'hello@example.com'), 'rules' => ['required', 'email', 'max:255']],
        'from_name' => ['type' => 'string', 'default' => (string) env('MAIL_FROM_NAME', 'HRM Plus'), 'rules' => ['required', 'string', 'max:100']],
    ],

    // Mobile push (Phase 8b). "simulated" records each push in the delivery log without sending it
    // (blocker B5: Firebase credentials not supplied yet); "fcm" uses the FCM HTTP v1 API with the uploaded
    // service-account key (encrypted at rest, never shown again — uploaded on this screen, not typed).
    'push' => [
        'provider' => ['type' => 'string', 'default' => 'simulated', 'rules' => ['required'], 'options' => ['simulated', 'fcm']],
        'fcm_service_account' => ['type' => 'secret', 'default' => null, 'rules' => ['nullable', 'string'], 'form' => false],
        'android_channel_id' => ['type' => 'string', 'default' => 'hrm_plus_default', 'rules' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.-]+$/']],
        'ttl_hours' => ['type' => 'integer', 'default' => 24, 'rules' => ['required', 'integer', 'between:1,672']],
    ],

    // Retention of operational records (Phase 8b), purged daily by system:purge-retention. Payroll,
    // attendance, audit and HR records are never purged by this job. Biometric retention is under
    // face_verification.
    'retention' => [
        'read_notifications_days' => ['type' => 'integer', 'default' => 180, 'rules' => ['required', 'integer', 'between:7,3650']],
        'delivery_log_days' => ['type' => 'integer', 'default' => 90, 'rules' => ['required', 'integer', 'between:7,3650']],
        'login_history_days' => ['type' => 'integer', 'default' => 730, 'rules' => ['required', 'integer', 'between:30,3650']],
        'inactive_device_token_days' => ['type' => 'integer', 'default' => 120, 'rules' => ['required', 'integer', 'between:7,3650']],
        // Files kept by the "daily" log channel (logging.channels.daily, formerly LOG_DAILY_DAYS).
        'log_days' => ['type' => 'integer', 'default' => 14, 'rules' => ['required', 'integer', 'between:1,365']],
    ],

    // HR requests and services (Phase 8b). Categories carry their own SLA; this is the fallback.
    'hr_requests' => [
        'employee_requests_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'default_sla_hours' => ['type' => 'integer', 'default' => 48, 'rules' => ['required', 'integer', 'between:1,2160']],
        // Requests past their due time are escalated (scheduled), then again every N hours up to the max level.
        'escalation_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'escalation_repeat_hours' => ['type' => 'integer', 'default' => 24, 'rules' => ['required', 'integer', 'between:1,336']],
        'max_escalation_level' => ['type' => 'integer', 'default' => 2, 'rules' => ['required', 'integer', 'between:1,5']],
        'attachment_max_kb' => ['type' => 'integer', 'default' => 5120, 'rules' => ['required', 'integer', 'between:100,20480']],
        'allowed_extensions' => [
            'type' => 'list', 'default' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'], 'rules' => ['array'],
            'options' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt'],
        ],
    ],

    // Announcements (Phase 8b): defaults of the publish form.
    'announcements' => [
        'email_by_default' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
        'push_by_default' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'attachment_max_kb' => ['type' => 'integer', 'default' => 10240, 'rules' => ['required', 'integer', 'between:100,20480']],
    ],

    'documents' => [
        'max_upload_size_kb' => ['type' => 'integer', 'default' => 10240, 'rules' => ['required', 'integer', 'between:100,102400']],
        'allowed_extensions' => [
            'type' => 'list', 'default' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'], 'rules' => ['array'],
            'options' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv'],
        ],
    ],

    // Reports centre exports (Phase 8c, formerly REPORTS_* in .env): exports with more rows than the
    // threshold are prepared by the queue worker and announced in the notification centre (0 = always
    // queued); PDF exports above pdf_max_rows are refused (Excel / CSV have no limit).
    'reports' => [
        'queue_threshold_xlsx' => ['type' => 'integer', 'default' => 5000, 'rules' => ['required', 'integer', 'between:0,1000000']],
        'queue_threshold_csv' => ['type' => 'integer', 'default' => 5000, 'rules' => ['required', 'integer', 'between:0,1000000']],
        'queue_threshold_pdf' => ['type' => 'integer', 'default' => 400, 'rules' => ['required', 'integer', 'between:0,20000', 'lte:pdf_max_rows']],
        'pdf_max_rows' => ['type' => 'integer', 'default' => 3000, 'rules' => ['required', 'integer', 'between:100,20000', 'gte:queue_threshold_pdf']],
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

    // Password policy, sessions and sign-in throttling (Phase 8b: applied at runtime). There is no
    // two-factor authentication yet (spec: optional).
    'security' => [
        'password_min_length' => ['type' => 'integer', 'default' => 8, 'rules' => ['required', 'integer', 'between:8,64']],
        'password_require_mixed_case' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
        'password_require_symbols' => ['type' => 'boolean', 'default' => false, 'rules' => ['boolean']],
        // Idle minutes before a web session (admin and portal) expires.
        'session_lifetime_minutes' => ['type' => 'integer', 'default' => (int) env('SESSION_LIFETIME', 120), 'rules' => ['required', 'integer', 'between:5,1440']],
        // Failed sign-ins per e-mail + IP (admin, portal and mobile API) before a lockout, and its length.
        'login_max_attempts' => ['type' => 'integer', 'default' => 5, 'rules' => ['required', 'integer', 'between:3,20']],
        'login_lockout_seconds' => ['type' => 'integer', 'default' => 60, 'rules' => ['required', 'integer', 'between:30,3600']],
        // Phase 10: forgot password in the mobile app — a 6-digit code e-mailed to the account (hashed, short-lived).
        'password_reset_code_minutes' => ['type' => 'integer', 'default' => 10, 'rules' => ['required', 'integer', 'between:3,60']],
        'password_reset_code_attempts' => ['type' => 'integer', 'default' => 5, 'rules' => ['required', 'integer', 'between:3,10']],
        // Formerly .env (SANCTUM_TOKEN_EXPIRATION, SESSION_*, SECURITY_*); applied by RuntimeSettings / read where used.
        // Mobile app bearer tokens: lifetime of a newly issued or refreshed token (applied to sanctum.expiration,
        // so lowering it also shortens tokens already issued).
        'api_token_lifetime_minutes' => ['type' => 'integer', 'default' => 43200, 'rules' => ['required', 'integer', 'between:60,525600']],
        // Session cookie: SameSite policy, and Secure flag (auto = Secure when APP_URL is https://). "none" forces Secure.
        'session_same_site' => ['type' => 'string', 'default' => 'lax', 'rules' => ['required'], 'options' => ['lax', 'strict', 'none']],
        'session_secure_cookie' => ['type' => 'string', 'default' => 'auto', 'rules' => ['required'], 'options' => ['auto', 'always', 'never']],
        // HTTP hardening (App\Http\Middleware\SecurityHeaders): security headers, Content-Security-Policy,
        // HSTS max-age (HTTPS only, 0 = off) and extra CSP origins for images / XHR (e.g. a storage CDN).
        'headers_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'csp_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'hsts_max_age_seconds' => ['type' => 'integer', 'default' => 31536000, 'rules' => ['required', 'integer', 'between:0,63072000']],
        'csp_extra_img_src' => ['type' => 'list', 'default' => [], 'rules' => ['array', 'max:20'], 'item_rules' => ['string', 'max:255', 'regex:'.$cspOrigin]],
        'csp_extra_connect_src' => ['type' => 'list', 'default' => [], 'rules' => ['array', 'max:20'], 'item_rules' => ['string', 'max:255', 'regex:'.$cspOrigin]],
        // Export / download requests per signed-in user and minute (rate limiter "exports").
        'exports_per_minute' => ['type' => 'integer', 'default' => 20, 'rules' => ['required', 'integer', 'between:1,600']],
    ],

    // Employee mobile app (Phase 10), served to the app by GET /api/v1/app/config. Versions are semantic
    // (major.minor.patch): an app older than min_version must update before it may be used.
    'mobile_app' => [
        'min_version' => ['type' => 'string', 'default' => '1.0.0', 'rules' => ['required', 'string', 'max:20', 'regex:/^\d+\.\d+\.\d+$/']],
        'latest_version' => ['type' => 'string', 'default' => '1.0.0', 'rules' => ['required', 'string', 'max:20', 'regex:/^\d+\.\d+\.\d+$/']],
        'android_store_url' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'url', 'max:255']],
        'ios_store_url' => ['type' => 'string', 'default' => '', 'rules' => ['nullable', 'url', 'max:255']],
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

    // Performance (Phase 8a).
    'performance' => [
        // Employees may add their own goals in the portal (the manager still reviews and weighs them).
        'employees_can_add_goals' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        // Days the employee has to acknowledge an approved review before HR is reminded.
        'acknowledgement_days' => ['type' => 'integer', 'default' => 14, 'rules' => ['required', 'integer', 'between:1,90']],
    ],

    // Training (Phase 8a).
    'training' => [
        'self_requests_enabled' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        // Certificates expiring within this many days are reminded (employee + training managers).
        'certificate_reminder_days' => ['type' => 'integer', 'default' => 30, 'rules' => ['required', 'integer', 'between:1,365']],
        // Attendance rate (%) needed to mark an enrolment completed.
        'completion_min_attendance_percent' => ['type' => 'integer', 'default' => 80, 'rules' => ['required', 'integer', 'between:0,100']],
    ],

    // Disciplinary actions (Phase 8a).
    'disciplinary' => [
        // Approved warnings count for escalation for this many months.
        'warning_validity_months' => ['type' => 'integer', 'default' => 12, 'rules' => ['required', 'integer', 'between:1,60']],
        // Days the employee has to respond to an issued action.
        'response_days' => ['type' => 'integer', 'default' => 7, 'rules' => ['required', 'integer', 'between:1,60']],
        // Deduction component (code) used for disciplinary salary deductions.
        'deduction_component' => ['type' => 'string', 'default' => 'PENALTY', 'rules' => ['nullable', 'string', 'max:40']],
    ],

    // Onboarding and offboarding (Phase 4).
    'lifecycle' => [
        'auto_start_onboarding' => ['type' => 'boolean', 'default' => true, 'rules' => ['boolean']],
        'contract_expiry_case_days' => ['type' => 'integer', 'default' => 30, 'rules' => ['required', 'integer', 'between:1,180']],
    ],

];
