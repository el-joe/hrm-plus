<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', Company::current());
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('country_code')) {
            $this->merge(['country_code' => mb_strtoupper(trim((string) $this->input('country_code')))]);
        }
    }

    /**
     * SVG is refused on purpose: it can carry scripts.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'legal_name_ar' => ['nullable', 'string', 'max:200'],
            'legal_name_en' => ['nullable', 'string', 'max:200'],
            'commercial_registration_no' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'url:http,https', 'max:200'],
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'address_ar' => ['nullable', 'string', 'max:500'],
            'address_en' => ['nullable', 'string', 'max:500'],
            'founded_on' => ['nullable', 'date', 'before_or_equal:today'],
            'logo' => ['nullable', Rule::imageFile()->extensions(['png', 'jpg', 'jpeg', 'webp'])->max(2048)->dimensions(Rule::dimensions()->maxWidth(2000)->maxHeight(2000))],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('structure.fields');
    }

    /**
     * Profile fields only (the logo is handled separately).
     *
     * @return array<string, mixed>
     */
    public function profile(): array
    {
        return $this->safe()->except(['logo', 'remove_logo']);
    }
}
