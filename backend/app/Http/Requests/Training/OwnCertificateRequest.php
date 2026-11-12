<?php

namespace App\Http\Requests\Training;

use App\Enums\CertificateType;
use App\Services\Training\TrainingFileStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A certificate / licence / qualification the employee uploads themselves (portal and API).
 * The file is required; type, size and extension follow Settings → Documents.
 */
class OwnCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->employee_id !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CertificateType::class)],
            'name' => ['required', 'string', 'max:200'],
            'issuer' => ['nullable', 'string', 'max:200'],
            'certificate_number' => ['nullable', 'string', 'max:100'],
            'issued_on' => ['nullable', 'date', 'before_or_equal:today'],
            'expires_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'file' => ['required', ...TrainingFileStore::rules()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function certificateData(): array
    {
        return collect($this->validated())->except('file')->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('training.fields');
    }
}
