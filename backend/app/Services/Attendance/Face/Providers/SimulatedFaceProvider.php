<?php

namespace App\Services\Attendance\Face\Providers;

use App\Models\Employee;
use App\Models\FaceEnrolment;
use App\Services\Attendance\Face\Contracts\FaceVerificationProvider;
use App\Services\Attendance\Face\Data\FaceEnrolmentResult;
use App\Services\Attendance\Face\Data\FaceMatchResult;
use App\Services\Attendance\Face\Data\FaceSample;
use App\Services\Attendance\Face\FaceProviderException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SIMULATED face provider for development. It performs NO biometric
 * matching and NO liveness detection: the outcome is chosen by the token the
 * app sends, so every path of the check-in flow can be exercised:
 *
 *   "sim-no-match"       not matched (score 0.21)
 *   "sim-liveness-fail"  liveness failed
 *   "sim-error"          provider error
 *   anything else        matched (score 0.97)
 *
 * The API and every screen label results of this provider "Simulated".
 * Never enable it in production.
 */
class SimulatedFaceProvider implements FaceVerificationProvider
{
    public function enrol(Employee $employee, FaceSample $sample): FaceEnrolmentResult
    {
        Log::info('[SIMULATED] face enrolment', ['employee_id' => $employee->id]);

        if ($sample->isEmpty()) {
            throw new FaceProviderException('sample_missing');
        }

        if ($sample->token === 'sim-error') {
            throw new FaceProviderException('provider_unavailable', 'Simulated provider error');
        }

        return new FaceEnrolmentResult('sim-'.Str::uuid(), 'SIMULATED-TEMPLATE:'.Str::random(32), 0.95);
    }

    public function verify(FaceEnrolment $enrolment, ?string $providerReference, ?string $template, FaceSample $sample): FaceMatchResult
    {
        Log::info('[SIMULATED] face verification', ['employee_id' => $enrolment->employee_id]);

        return match ($sample->token) {
            'sim-error' => throw new FaceProviderException('provider_unavailable', 'Simulated provider error'),
            'sim-no-match' => new FaceMatchResult(false, 0.21, $sample->livenessPassed ?? true, $sample->livenessScore ?? 0.9, 'sim-'.Str::random(8)),
            'sim-liveness-fail' => new FaceMatchResult(true, 0.95, false, 0.12, 'sim-'.Str::random(8)),
            default => new FaceMatchResult(true, 0.97, $sample->livenessPassed ?? true, $sample->livenessScore ?? 0.93, 'sim-'.Str::random(8)),
        };
    }

    public function delete(FaceEnrolment $enrolment, ?string $providerReference): void
    {
        Log::info('[SIMULATED] face subject deleted', ['enrolment_id' => $enrolment->id]);
    }
}
