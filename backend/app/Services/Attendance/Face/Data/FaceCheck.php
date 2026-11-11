<?php

namespace App\Services\Attendance\Face\Data;

use App\Enums\FaceVerificationResult;

/**
 * Decision of FaceVerifier for one mobile attempt.
 *
 * outcome:
 *   pass       nothing blocks the attempt (matched, mode off, or optional without a sample)
 *   missing    required but no sample sent: rejected, the app must capture the face
 *   retry      failed, attempts left: rejected, the employee may try again
 *   fallback   failed for good (attempts used up / not enrolled / optional failure):
 *              the policy's face_failure_action decides (review queue or rejection)
 *
 * attributes: the face_verifications row to store (null: nothing to store).
 */
final readonly class FaceCheck
{
    /**
     * @param  array<string, mixed>|null  $attributes
     */
    public function __construct(
        public string $outcome,
        public FaceVerificationResult $result,
        public int $remainingAttempts,
        public ?array $attributes = null,
    ) {}
}
