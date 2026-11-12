<?php

namespace App\Http\Resources\V1;

use App\Models\TrainingCertificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An employee's own certificate / licence / qualification. "validity": valid |
 * expiring (within the reminder window) | expired | no_expiry. The file is fetched
 * from download_url (authenticated, own records only).
 *
 * @mixin TrainingCertificate
 */
class TrainingCertificateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'name' => $this->name,
            'issuer' => $this->issuer,
            'certificate_number' => $this->certificate_number,
            'course' => $this->course?->name,
            'issued_on' => $this->issued_on?->toDateString(),
            'expires_on' => $this->expires_on?->toDateString(),
            'validity' => $this->validityState(),
            'validity_label' => __('training.validity.'.$this->validityState()),
            'has_file' => $this->hasFile(),
            'file_name' => $this->file_name,
            'download_url' => $this->hasFile() ? route('api.v1.me.training.certificates.download', $this->id) : null,
        ];
    }
}
