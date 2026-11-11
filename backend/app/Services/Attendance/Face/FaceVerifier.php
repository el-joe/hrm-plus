<?php

namespace App\Services\Attendance\Face;

use App\Enums\FaceVerificationMode;
use App\Enums\FaceVerificationResult;
use App\Enums\PunchDirection;
use App\Models\Employee;
use App\Models\FaceVerification;
use App\Services\Attendance\Face\Data\FaceCheck;
use App\Services\Attendance\Face\Data\FaceSample;
use Carbon\CarbonImmutable;

/**
 * Face verification of a mobile check-in / check-out, by the policy's mode:
 *
 * - off: skipped;
 * - optional: verified when the app sends a sample; a failure is never
 *   silently accepted (fallback -> review / rejection by policy);
 * - required: a sample is needed; failures count against face_max_attempts
 *   per employee, date and direction; when they are used up (or the
 *   employee is not enrolled) the policy's face_failure_action decides:
 *   send to the review queue (manual / HR fallback) or reject.
 *
 * Liveness: a negative liveness flag from the provider (or passed through
 * from its mobile SDK) is a failure.
 */
class FaceVerifier
{
    public function __construct(
        private readonly FaceProviderRegistry $providers,
        private readonly FaceEnrolmentService $enrolments,
        private readonly BiometricStore $store,
    ) {}

    public function check(Employee $employee, FaceVerificationMode $mode, ?FaceSample $sample, int $maxAttempts, PunchDirection $direction, CarbonImmutable $workDate): FaceCheck
    {
        $key = $this->providers->key();
        $base = [
            'employee_id' => $employee->id, 'provider' => $key, 'is_simulated' => $this->providers->isSimulated($key),
            'direction' => $direction, 'work_date' => $workDate->format('Y-m-d'), 'verified_at' => now(),
        ];

        if ($mode === FaceVerificationMode::Off) {
            return new FaceCheck('pass', FaceVerificationResult::Skipped, 0);
        }

        $required = $mode === FaceVerificationMode::Required;
        $failures = $this->failuresToday($employee, $direction, $workDate);
        $remaining = max(0, $maxAttempts - $failures);
        $hasSample = $sample !== null && ! $sample->isEmpty();
        $enrolment = $this->enrolments->activeEnrolment($employee);

        if (! $hasSample) {
            return $required
                ? new FaceCheck('missing', FaceVerificationResult::NotProvided, $remaining)
                : new FaceCheck('pass', FaceVerificationResult::NotProvided, $remaining, [...$base, 'result' => FaceVerificationResult::NotProvided]);
        }

        if ($enrolment === null) {
            return new FaceCheck('fallback', FaceVerificationResult::NotEnrolled, $remaining, [...$base, 'result' => FaceVerificationResult::NotEnrolled]);
        }

        if ($remaining === 0) {
            return new FaceCheck('fallback', FaceVerificationResult::NotMatched, 0, null);
        }

        $attempt = $failures + 1;
        $row = [...$base, 'face_enrolment_id' => $enrolment->id, 'attempt_number' => $attempt];

        try {
            $match = $this->providers->make($enrolment->provider)->verify(
                $enrolment, $enrolment->provider_reference, $this->store->get($enrolment->template_path), $sample,
            );
            $liveness = $match->livenessPassed ?? $sample->livenessPassed;
            $result = match (true) {
                $liveness === false => FaceVerificationResult::LivenessFailed,
                $match->matched => FaceVerificationResult::Matched,
                default => FaceVerificationResult::NotMatched,
            };
            $row += [
                'result' => $result, 'score' => $match->score, 'liveness_passed' => $liveness,
                'liveness_score' => $match->livenessScore ?? $sample->livenessScore, 'provider_reference' => $match->providerReference,
            ];
        } catch (FaceProviderException $exception) {
            $result = FaceVerificationResult::ProviderError;
            $row += ['result' => $result, 'error_code' => mb_substr($exception->reason, 0, 60)];
        }

        if ($result->isSuccess()) {
            return new FaceCheck('pass', $result, $remaining, $row);
        }

        $remaining = max(0, $maxAttempts - $attempt);

        return new FaceCheck($required && $remaining > 0 ? 'retry' : 'fallback', $result, $remaining, $row);
    }

    public function failuresToday(Employee $employee, PunchDirection $direction, CarbonImmutable $workDate): int
    {
        return FaceVerification::query()->where('employee_id', $employee->id)->where('direction', $direction)
            ->whereDate('work_date', $workDate->format('Y-m-d'))
            ->whereIn('result', [FaceVerificationResult::NotMatched, FaceVerificationResult::LivenessFailed, FaceVerificationResult::ProviderError])
            ->count();
    }
}
