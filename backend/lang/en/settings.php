<?php

/*
| Labels for the settings registry (config/settings.php): groups, keys and option values.
*/

return [

    'groups' => [
        'localisation' => 'Localisation',
        'attendance' => 'Attendance',
        'payroll' => 'Payroll',
        'leave' => 'Leave',
        'notifications' => 'Notifications',
        'documents' => 'Documents',
        'employee_number' => 'Employee number',
        'security' => 'Security',
        'approvals' => 'Approvals',
        'recruitment' => 'Recruitment',
        'lifecycle' => 'Onboarding & offboarding',
        'face_verification' => 'Face verification',
    ],
    'keys' => [
        'localisation' => [
            'default_locale' => 'Default language',
            'timezone' => 'Timezone',
            'date_format' => 'Date format',
            'time_format' => 'Time format',
            'currency_code' => 'Currency code (ISO)',
            'currency_decimals' => 'Currency decimal places',
            'use_arabic_digits' => 'Use Arabic-Indic digits',
            'first_day_of_week' => 'First day of the week',
        ],
        'attendance' => [
            'working_days' => 'Working days',
            'late_grace_minutes' => 'Late arrival grace (minutes)',
            'early_leave_grace_minutes' => 'Early departure grace (minutes)',
            'overtime_minimum_minutes' => 'Minimum overtime (minutes)',
            'geofence_default_radius_meters' => 'Default geofence radius (metres)',
            'gps_max_accuracy_meters' => 'Worst accepted GPS accuracy (metres)',
            'face_verification_required' => 'Require face verification on mobile check-in',
            'face_verification_max_retries' => 'Face verification retries',
            'manual_entry_requires_approval' => 'Manual attendance requires approval',
            'gps_accuracy_tolerance_meters' => 'GPS accuracy credited to the radius (metres, default for new policies)',
            'gps_max_time_drift_seconds' => 'Allowed phone clock difference (seconds)',
            'gps_repeated_attempts_threshold' => 'Rejected mobile attempts per hour before review',
            'device_stale_after_hours' => 'Device without contact for (hours) needs attention',
        ],
        'payroll' => [
            'period_start_day' => 'Payroll period start day',
            'working_days_per_month' => 'Working days per month',
            'working_hours_per_day' => 'Working hours per day',
            'overtime_rate_multiplier' => 'Overtime rate multiplier',
            'rounding_precision' => 'Rounding decimal places',
        ],
        'leave' => [
            'year_start_month' => 'Leave year start month',
            'allow_half_day' => 'Allow half-day leave',
            'allow_negative_balance' => 'Allow negative balance',
            'count_weekends_as_leave' => 'Count weekly days off inside a leave',
            'max_past_days' => 'Past dates employees may request (days)',
            'cancel_before_start_directly' => 'Cancel approved leave before it starts without approval',
            'calendar_conflict_threshold' => 'Leave calendar: flag a department when this many are away',
        ],
        'notifications' => [
            'email_enabled' => 'Send email notifications',
            'push_enabled' => 'Send push notifications',
            'contract_expiry_reminder_days' => 'Contract expiry reminder (days before)',
            'document_expiry_reminder_days' => 'Document expiry reminder (days before)',
        ],
        'documents' => [
            'max_upload_size_kb' => 'Maximum upload size (KB)',
            'allowed_extensions' => 'Allowed file types',
        ],
        'employee_number' => [
            'pattern' => 'Pattern ({prefix}, {number}, {year}, {branch})',
            'prefix' => 'Prefix',
            'padding' => 'Number of digits',
            'next_sequence' => 'Next number',
        ],
        'security' => [
            'password_min_length' => 'Minimum password length',
        ],
        'approvals' => [
            'allow_self_approval' => 'Allow approving your own requests',
        ],
        'recruitment' => [
            'career_page_enabled' => 'Public career page enabled',
            'career_applications_per_hour' => 'Career page applications per visitor per hour',
            'cv_max_size_kb' => 'Maximum CV size (KB)',
            'cv_allowed_extensions' => 'Allowed CV file types',
            'candidate_emails_enabled' => 'Send e-mails to candidates',
            'interview_reminder_hours' => 'Interview reminder (hours before)',
            'offer_requires_approval' => 'Job offers require approval',
            'offer_validity_days' => 'Default offer validity (days)',
        ],
        'lifecycle' => [
            'auto_start_onboarding' => 'Start onboarding automatically when a candidate is hired',
            'contract_expiry_case_days' => 'Open offboarding cases for contracts ending within (days)',
        ],
        'face_verification' => [
            'provider' => 'Face verification provider',
            'consent_version' => 'Consent text version',
            'enrolment_max_age_days' => 'Re-enrol after (days)',
            'verification_log_retention_days' => 'Keep verification results (days)',
            'store_enrolment_image' => 'Keep an encrypted enrolment image',
        ],
    ],
    'options' => [
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'ar' => 'العربية',
        'en' => 'English',
        'simulated' => 'Simulated (development only)',
    ],

];
