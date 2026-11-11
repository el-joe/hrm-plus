<?php

namespace App\Services\Attendance\Face\Contracts;

use App\Models\Employee;
use App\Models\FaceEnrolment;
use App\Services\Attendance\Face\Data\FaceEnrolmentResult;
use App\Services\Attendance\Face\Data\FaceMatchResult;
use App\Services\Attendance\Face\Data\FaceSample;
use App\Services\Attendance\Face\FaceProviderException;

/**
 * Abstraction over a face verification SDK / service, so the provider can be
 * changed without touching attendance. A real provider must do the matching
 * and liveness / anti-spoofing itself (provider-side or certified on-device
 * SDK); HRM Plus never compares images on its own.
 *
 * Register implementations in config/face_verification.php.
 */
interface FaceVerificationProvider
{
    /**
     * Enrol the employee's face. Returns the provider's subject reference and,
     * only for providers that hand back a template, the template bytes (they
     * are encrypted before storage).
     *
     * @throws FaceProviderException
     */
    public function enrol(Employee $employee, FaceSample $sample): FaceEnrolmentResult;

    /**
     * Match a sample captured at check-in against the enrolment.
     *
     * @param  ?string  $template  decrypted template, when the provider stored one
     *
     * @throws FaceProviderException
     */
    public function verify(FaceEnrolment $enrolment, ?string $providerReference, ?string $template, FaceSample $sample): FaceMatchResult;

    /**
     * Delete the subject on the provider side (consent withdrawn, termination, retention).
     */
    public function delete(FaceEnrolment $enrolment, ?string $providerReference): void;
}
