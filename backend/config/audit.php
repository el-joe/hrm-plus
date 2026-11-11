<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redacted attributes
    |--------------------------------------------------------------------------
    |
    | Any attribute or array key whose name contains one of these fragments is
    | never written to the audit log; its value is replaced by the placeholder.
    | A model's $hidden attributes are redacted as well. Matching is
    | case-insensitive and applies to nested arrays.
    |
    */

    'redacted_fragments' => [
        'password',
        'token',
        'secret',
        'api_key',
        'apikey',
        'private_key',
        'two_factor',
        'recovery_code',
        'biometric',
        'face_template',
        'credential',
        'authorization',
    ],

    'redacted_placeholder' => '[REDACTED]',

    /*
    | Attributes that carry no audit value and are skipped entirely.
    */
    'ignored_attributes' => ['created_at', 'updated_at', 'deleted_at'],

];
