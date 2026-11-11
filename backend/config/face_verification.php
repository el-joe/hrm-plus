<?php

use App\Services\Attendance\Face\Providers\SimulatedFaceProvider;

/*
|--------------------------------------------------------------------------
| Face verification providers (Phase 5b)
|--------------------------------------------------------------------------
|
| Each provider implements App\Services\Attendance\Face\Contracts\FaceVerificationProvider.
| The provider is chosen in Settings → Face verification. No real provider is
| configured yet (PROJECT_PLAN.md, blocker B4): "simulated" exists for
| development only, never performs biometric matching and is labelled
| "Simulated" in the API and every screen. See docs/FACE_VERIFICATION.md.
|
*/

return [

    'providers' => [
        'simulated' => [
            'class' => SimulatedFaceProvider::class,
            'simulated' => true,
        ],
    ],

    // Private disk for encrypted templates / enrolment images.
    'disk' => 'biometric_files',

    // Enrolment upload limits (images sent by the app for providers that need them).
    'max_image_kb' => 4096,
    'max_images' => 3,

];
