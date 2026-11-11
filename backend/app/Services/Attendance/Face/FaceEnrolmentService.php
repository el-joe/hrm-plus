<?php

namespace App\Services\Attendance\Face;

use App\Enums\EmployeeStatus;
use App\Enums\FaceEnrolmentStatus;
use App\Models\Employee;
use App\Models\FaceConsent;
use App\Models\FaceEnrolment;
use App\Models\FaceVerification;
use App\Models\User;
use App\Services\Attendance\Face\Data\FaceSample;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Consent, enrolment and deletion of biometric face data.
 *
 * - Enrolment needs an active consent for the CURRENT consent version
 *   (Settings → Face verification → consent version); a new version asks
 *   every employee again.
 * - Withdrawing consent, termination (EmployeeTerminated) and the retention
 *   purge (face:purge, daily) delete the provider subject, the encrypted
 *   files and the reference; the enrolment row keeps only dates and reason.
 * - Every step is audited; no biometric value ever reaches the audit log.
 */
class FaceEnrolmentService
{
    public function __construct(
        private readonly FaceProviderRegistry $providers,
        private readonly BiometricStore $store,
        private readonly AuditLogger $audit,
    ) {}

    public function currentConsentVersion(): string
    {
        return (string) setting('face_verification.consent_version');
    }

    public function activeConsent(Employee $employee): ?FaceConsent
    {
        return FaceConsent::query()->where('employee_id', $employee->id)->whereNull('withdrawn_at')->latest('id')->first();
    }

    public function hasCurrentConsent(Employee $employee): bool
    {
        return $this->activeConsent($employee)?->version === $this->currentConsentVersion();
    }

    public function activeEnrolment(Employee $employee): ?FaceEnrolment
    {
        return FaceEnrolment::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->where('status', FaceEnrolmentStatus::Active)->latest('id')->first();
    }

    public function giveConsent(Employee $employee, string $source, ?User $user, ?string $ip): FaceConsent
    {
        $current = $this->activeConsent($employee);

        if ($current?->version === $this->currentConsentVersion()) {
            return $current;
        }

        return DB::transaction(function () use ($employee, $current, $source, $user, $ip): FaceConsent {
            $current?->forceFill(['withdrawn_at' => now()])->save();

            return FaceConsent::query()->create([
                'employee_id' => $employee->id, 'version' => $this->currentConsentVersion(), 'given_at' => now(),
                'source' => $source, 'user_id' => $user?->id, 'ip_address' => $ip,
            ]);
        });
    }

    /**
     * Withdraw consent: the biometric data is deleted at once.
     */
    public function withdrawConsent(Employee $employee, ?User $user): void
    {
        DB::transaction(function () use ($employee, $user): void {
            FaceConsent::query()->where('employee_id', $employee->id)->whereNull('withdrawn_at')->get()
                ->each(fn (FaceConsent $consent) => $consent->forceFill(['withdrawn_at' => now()])->save());
            $this->deleteBiometrics($employee, 'consent_withdrawn', $user);
        });
    }

    /**
     * @throws FaceProviderException consent_required | sample_missing | provider reasons
     */
    public function enrol(Employee $employee, FaceSample $sample, ?User $user): FaceEnrolment
    {
        $consent = $this->activeConsent($employee);

        if ($consent === null || $consent->version !== $this->currentConsentVersion()) {
            throw new FaceProviderException('consent_required');
        }

        if ($sample->isEmpty()) {
            throw new FaceProviderException('sample_missing');
        }

        $key = $this->providers->key();
        $result = $this->providers->current()->enrol($employee, $sample);
        $this->deleteBiometrics($employee, 're_enrolment', $user, audit: false);

        $templatePath = $result->template !== null ? $this->store->put($employee->id, 'template', $result->template) : null;
        $imagePath = setting('face_verification.store_enrolment_image') && $sample->images !== []
            ? $this->store->put($employee->id, 'image', $sample->images[0])
            : null;

        $enrolment = FaceEnrolment::query()->create([
            'employee_id' => $employee->id,
            'face_consent_id' => $consent->id,
            'provider' => $key,
            'is_simulated' => $this->providers->isSimulated($key),
            'status' => FaceEnrolmentStatus::Active,
            'provider_reference' => $result->providerReference,
            'template_path' => $templatePath,
            'image_path' => $imagePath,
            'quality_score' => $result->qualityScore,
            'enrolled_at' => now(),
            'enrolled_by' => $user?->id,
        ]);

        $this->audit->log('face.enrolled', $enrolment, meta: ['employee_id' => $employee->id, 'provider' => $key, 'simulated' => $enrolment->is_simulated], actor: $user);

        return $enrolment;
    }

    /**
     * Delete every active enrolment of the employee (provider subject, files, reference).
     *
     * @param  'consent_withdrawn'|'termination'|'retention'|'admin'|'re_enrolment'  $reason
     */
    public function deleteBiometrics(Employee $employee, string $reason, ?User $user, bool $audit = true): int
    {
        $enrolments = FaceEnrolment::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->where('status', FaceEnrolmentStatus::Active)->get();

        foreach ($enrolments as $enrolment) {
            $this->deleteEnrolment($enrolment, $reason, $user, $audit);
        }

        return $enrolments->count();
    }

    /**
     * @param  'consent_withdrawn'|'termination'|'retention'|'admin'|'re_enrolment'  $reason
     */
    public function deleteEnrolment(FaceEnrolment $enrolment, string $reason, ?User $user, bool $audit = true, ?string $note = null): void
    {
        try {
            $this->providers->make($enrolment->provider)->delete($enrolment, $enrolment->provider_reference);
        } catch (\Throwable $exception) {
            report($exception);
        }

        $this->store->delete($enrolment->template_path);
        $this->store->delete($enrolment->image_path);

        $enrolment->forceFill([
            'status' => FaceEnrolmentStatus::Deleted, 'provider_reference' => null, 'template_path' => null, 'image_path' => null,
            'deleted_at' => now(), 'deletion_reason' => $reason, 'deleted_by' => $user?->id,
        ])->save();

        if ($audit) {
            $this->audit->log('face.enrolment_deleted', $enrolment, meta: array_filter(['employee_id' => $enrolment->employee_id, 'reason' => $reason, 'note' => $note]), actor: $user);
        }
    }

    /**
     * Retention: enrolments older than the maximum age, enrolments of
     * terminated employees, and verification results older than the log
     * retention period.
     *
     * @return array{enrolments: int, verifications: int}
     */
    public function purge(): array
    {
        $maxAge = now()->subDays((int) setting('face_verification.enrolment_max_age_days'));
        $enrolments = 0;

        FaceEnrolment::query()->withoutGlobalScopes()->where('status', FaceEnrolmentStatus::Active)
            ->with(['employee' => fn ($query) => $query->withoutGlobalScopes()])
            ->get()
            ->each(function (FaceEnrolment $enrolment) use ($maxAge, &$enrolments): void {
                $terminated = $enrolment->employee?->status === EmployeeStatus::Terminated;

                if ($terminated || $enrolment->enrolled_at->lt($maxAge)) {
                    $this->deleteEnrolment($enrolment, $terminated ? 'termination' : 'retention', null);
                    $enrolments++;
                }
            });

        $verifications = FaceVerification::query()
            ->where('verified_at', '<', now()->subDays((int) setting('face_verification.verification_log_retention_days')))
            ->delete();

        if ($enrolments > 0 || $verifications > 0) {
            $this->audit->log('face.retention_purged', meta: ['enrolments' => $enrolments, 'verifications' => $verifications]);
        }

        return ['enrolments' => $enrolments, 'verifications' => $verifications];
    }

    /**
     * The employee's own status (API and portal).
     *
     * @return array<string, mixed>
     */
    public function status(Employee $employee): array
    {
        $consent = $this->activeConsent($employee);
        $enrolment = $this->activeEnrolment($employee);
        $key = $this->providers->key();

        return [
            'provider' => $key,
            'is_simulated' => $this->providers->isSimulated($key),
            'consent' => [
                'current_version' => $this->currentConsentVersion(),
                'given' => $consent !== null && $consent->version === $this->currentConsentVersion(),
                'given_version' => $consent?->version,
                'given_at' => $consent?->given_at?->toIso8601ZuluString(),
            ],
            'enrolment' => $enrolment === null ? null : [
                'enrolled_at' => $enrolment->enrolled_at->toIso8601ZuluString(),
                'provider' => $enrolment->provider,
                'is_simulated' => $enrolment->is_simulated,
                'expires_at' => $enrolment->enrolled_at->addDays((int) setting('face_verification.enrolment_max_age_days'))->toIso8601ZuluString(),
            ],
            'enrolled' => $enrolment !== null,
        ];
    }
}
