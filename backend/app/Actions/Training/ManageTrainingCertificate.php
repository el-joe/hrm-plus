<?php

namespace App\Actions\Training;

use App\Enums\CertificateType;
use App\Models\Employee;
use App\Models\TrainingCertificate;
use App\Models\TrainingEnrolment;
use App\Models\TrainingSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Training\TrainingFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Certificates, licences and qualifications of employees: recorded / uploaded by
 * HR or by the employee (portal, API), or issued automatically when a course that
 * issues certificates is completed (expiry = issue date + the course's validity).
 * Replacing a file deletes the old one; deleting a record deletes its file.
 */
class ManageTrainingCertificate
{
    public function __construct(private readonly TrainingFileStore $files, private readonly AuditLogger $audit) {}

    /**
     * @param  array{type: string, name: string, issuer?: string|null, certificate_number?: string|null, issued_on?: string|null, expires_on?: string|null, training_course_id?: int|null}  $data
     */
    public function store(Employee $employee, array $data, ?UploadedFile $file, User $actor): TrainingCertificate
    {
        return DB::transaction(function () use ($employee, $data, $file, $actor): TrainingCertificate {
            $certificate = TrainingCertificate::create([
                ...$this->attributes($data),
                'employee_id' => $employee->id,
                'uploaded_by' => $actor->id,
                ...($file ? $this->files->store($employee->id, $file) : []),
            ]);

            $this->audit->log('training.certificate_added', $certificate, meta: ['type' => $certificate->type->value, 'file' => $file !== null], actor: $actor);

            return $certificate;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(TrainingCertificate $certificate, array $data, ?UploadedFile $file, User $actor): TrainingCertificate
    {
        $old = $certificate->file_path;
        $attributes = $this->attributes($data);

        if ($certificate->expires_on?->toDateString() !== ($attributes['expires_on'] ?? null)) {
            $attributes['reminder_sent_at'] = null;
        }

        $certificate->forceFill([...$attributes, ...($file ? $this->files->store($certificate->employee_id, $file) : [])])->save();

        if ($file && $old) {
            $this->files->delete($old);
        }

        return $certificate;
    }

    public function delete(TrainingCertificate $certificate, User $actor): void
    {
        $path = $certificate->file_path;
        $certificate->delete();
        $this->files->delete($path);
        $this->audit->log('training.certificate_deleted', null, meta: ['certificate' => $certificate->id, 'employee' => $certificate->employee_id], actor: $actor);
    }

    /**
     * The certificate of a completed course (no file: the provider's document can be attached later).
     */
    public function issueForEnrolment(TrainingEnrolment $enrolment, TrainingSession $session, User $actor): TrainingCertificate
    {
        $course = $session->course;

        return TrainingCertificate::create([
            'employee_id' => $enrolment->employee_id,
            'training_enrolment_id' => $enrolment->id,
            'training_course_id' => $course->id,
            'type' => CertificateType::Certificate,
            'name' => $course->localized('name', (string) setting('localisation.default_locale')) ?? $course->code,
            'issuer' => $course->provider?->localized('name', (string) setting('localisation.default_locale')),
            'issued_on' => $session->end_date->toDateString(),
            'expires_on' => $course->certificate_validity_months ? $session->end_date->copy()->addMonthsNoOverflow($course->certificate_validity_months)->toDateString() : null,
            'uploaded_by' => $actor->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'type' => CertificateType::from($data['type'] ?? CertificateType::Certificate->value),
            'name' => $data['name'],
            'issuer' => $data['issuer'] ?? null,
            'certificate_number' => $data['certificate_number'] ?? null,
            'issued_on' => $data['issued_on'] ?? null,
            'expires_on' => $data['expires_on'] ?? null,
            'training_course_id' => $data['training_course_id'] ?? null,
        ];
    }
}
