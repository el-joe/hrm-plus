<?php

namespace App\Http\Requests\Careers;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Public career page application. No account; protected by the
 * "career-apply" rate limiter and a honeypot field ("website") that people
 * never see and bots fill in. CV type and size come from the recruitment settings.
 */
class CareerApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $types = implode(',', (array) setting('recruitment.cv_allowed_extensions'));

        return [
            'website' => ['nullable', 'max:0'],
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'min:6', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'current_job_title' => ['nullable', 'string', 'max:150'],
            'experience_years' => ['nullable', 'decimal:0,1', 'min:0', 'max:60'],
            'linkedin_url' => ['nullable', 'url:https,http', 'max:255'],
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'cv' => ['required', 'file', 'mimes:'.$types, 'extensions:'.$types, 'max:'.(int) setting('recruitment.cv_max_size_kb')],
            'consent' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('recruitment.career.fields');
    }
}
