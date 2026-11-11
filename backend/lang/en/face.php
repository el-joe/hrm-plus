<?php

/*
| Face verification: consent, enrolment, verification results (Phase 5b).
*/

return [

    'providers' => [
        'simulated' => 'Simulated provider (development)',
    ],

    'enums' => [
        'modes' => ['off' => 'Off', 'optional' => 'Optional', 'required' => 'Required'],
        'results' => [
            'matched' => 'Matched', 'not_matched' => 'Not matched', 'liveness_failed' => 'Liveness failed', 'not_enrolled' => 'Not enrolled',
            'not_provided' => 'Not provided', 'provider_error' => 'Provider error', 'skipped' => 'Skipped',
        ],
        'enrolment_statuses' => ['active' => 'Enrolled', 'deleted' => 'Deleted'],
    ],

    'deletion_reasons' => [
        'consent_withdrawn' => 'Consent withdrawn',
        'termination' => 'Employment ended',
        'retention' => 'Retention period over',
        'admin' => 'Deleted by an administrator',
        're_enrolment' => 'Replaced by a new enrolment',
    ],

    'fields' => [
        'attempt' => 'Attempt',
        'result' => 'Result',
        'score' => 'Score',
        'liveness' => 'Liveness',
        'provider' => 'Provider',
        'consent' => 'Consent',
        'enrolled_at' => 'Enrolled',
    ],

    'admin' => [
        'title' => 'Face enrolments',
        'subtitle' => 'Consent and enrolment register. Biometric data is never displayed.',
        'simulated_title' => 'Simulated face provider',
        'simulated_help' => 'No face verification provider is configured: the simulated provider performs no biometric matching or liveness detection. Do not rely on it outside development; see docs/FACE_VERIFICATION.md.',
        'privacy_title' => 'Biometric data',
        'privacy_help' => 'Templates and images are encrypted on a private disk and never shown. Data is deleted when consent is withdrawn, when employment ends and after the retention period. Opening this page is logged.',
        'active_enrolments' => 'Active enrolments',
        'active_consents' => 'Active consents',
        'consent_version' => 'Current consent version',
        'consent_version_n' => 'Version :version',
        'withdrawn' => 'Withdrawn',
        'last_30_days' => 'Verifications (30 days)',
        'delete' => 'Delete biometric data',
        'confirm_delete' => 'Delete this employee\'s face enrolment? The provider record and encrypted files are erased; the employee must enrol again.',
        'deleted' => 'Biometric data deleted.',
        'empty' => 'No enrolments yet',
        'empty_help' => 'Employees enrol from the mobile app after giving consent.',
    ],

    'api' => [
        'consent_given' => 'Consent recorded.',
        'consent_withdrawn' => 'Consent withdrawn; your face data was deleted.',
        'outdated_consent' => 'Please accept the current version of the consent text.',
        'enrolled' => 'Face enrolled.',
        'errors' => [
            'consent_required' => 'Give your consent before enrolling.',
            'sample_missing' => 'No face capture was received.',
            'provider_unavailable' => 'Face verification is unavailable right now. Try again later.',
        ],
    ],

];
