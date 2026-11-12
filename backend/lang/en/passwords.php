<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Password Reset Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are the default lines which match reasons
    | that are given by the password broker for a password update attempt
    | outcome such as failure due to an invalid password / reset token.
    |
    */

    'reset' => 'Your password has been reset.',
    'sent' => 'If that email address is registered, a password reset link has been sent to it.',
    'throttled' => 'Please wait before retrying.',
    'token' => 'This password reset link is invalid or has expired.',
    'user' => "We can't find a user with that email address.",

    // Mobile app: one-time code by e-mail (Phase 10).
    'code' => [
        'subject' => ':app password reset code',
        'greeting' => 'Hello :name,',
        'intro' => 'Use this code in the app to reset your password:',
        'expires' => 'The code expires in :minutes minutes and can be used once.',
        'ignore' => 'If you did not ask to reset your password, ignore this e-mail; your password stays unchanged.',
        'sent' => 'If that e-mail address belongs to an employee account, a reset code has been sent to it.',
        'invalid' => 'The code is wrong or has expired. Request a new code.',
        'valid' => 'Code verified. Choose a new password.',
    ],
    'changed' => 'Your password has been changed. Other devices and browsers were signed out.',
    'current_wrong' => 'The current password is not correct.',

];
