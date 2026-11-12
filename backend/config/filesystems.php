<?php

// Local disks: files are shared with the web server's group (the storage tree is group www-data, setgid),
// so files written by CLI jobs/seeders stay readable by PHP under Apache. "Other" never gets access.
$groupReadable = [
    'file' => ['public' => 0664, 'private' => 0640],
    'dir' => ['public' => 0775, 'private' => 0750],
];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'permissions' => $groupReadable,
            'root' => storage_path('app/private'),
            // Never served over HTTP (Phase 11): private files are only streamed by authorised controllers.
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        // Employee photos, documents, contract scans and import uploads. Private:
        // no URL, never served directly; only authorised controllers stream
        // these files (and log each document download).
        'employee_files' => [
            'driver' => 'local',
            'permissions' => $groupReadable,
            'root' => storage_path('app/private/employees'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        // Candidate CVs and attachments (recruitment); never public.
        'recruitment_files' => [
            'driver' => 'local',
            'permissions' => $groupReadable,
            'root' => storage_path('app/private/recruitment'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        // Face enrolment templates / images (Phase 5b). Contents are encrypted
        // with the application key before they are written; never public.
        'biometric_files' => [
            'driver' => 'local',
            'permissions' => $groupReadable,
            'root' => storage_path('app/private/biometric'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        // Training certificates (Phase 8a).
        'training_files' => [
            'driver' => 'local',
            'permissions' => $groupReadable,
            'root' => storage_path('app/private/training'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        // Disciplinary evidence (Phase 8a): restricted, streamed only after the policy check.
        'disciplinary_files' => [
            'driver' => 'local',
            'permissions' => $groupReadable,
            'root' => storage_path('app/private/disciplinary'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        // HR request attachments and issued letters (Phase 8b): streamed only after the policy / ownership check.
        'hr_request_files' => [
            'driver' => 'local',
            'permissions' => $groupReadable,
            'root' => storage_path('app/private/hr-requests'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        // Announcement attachments (Phase 8b): streamed to admins and to employees in the audience only.
        'announcement_files' => [
            'driver' => 'local',
            'permissions' => $groupReadable,
            'root' => storage_path('app/private/announcements'),
            'visibility' => 'private',
            'throw' => true,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'permissions' => $groupReadable,
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
