<?php

namespace App\Http\Requests\Admin\Recruitment;

use App\Enums\CandidateSource;
use App\Enums\Gender;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\Vacancy;
use App\Rules\ExistsInScope;
use App\Services\Recruitment\CandidateDuplicates;
use App\Services\Recruitment\SalaryVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / edit a candidate. At least one name; e-mail or phone required.
 * Duplicates (same e-mail or phone, company-wide) stop the save until the
 * admin confirms with confirm_duplicate. Admins with data restrictions must
 * attach a new candidate to a vacancy in their scope (or they could not see
 * the record afterwards). Salaries only from admins who may see them.
 */
class CandidateRequest extends FormRequest
{
    public function candidate(): ?Candidate
    {
        $candidate = $this->route('candidate');

        return $candidate instanceof Candidate ? $candidate : null;
    }

    public function authorize(): bool
    {
        return $this->candidate() ? $this->user()->can('update', $this->candidate()) : $this->user()->can('create', Candidate::class);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }

        if (is_string($this->input('nationality'))) {
            $this->merge(['nationality' => mb_strtoupper(trim($this->input('nationality')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $salary = app(SalaryVisibility::class)->allows($this->user());
        $creating = $this->candidate() === null;
        $cvTypes = implode(',', (array) setting('recruitment.cv_allowed_extensions'));

        return [
            'name_ar' => ['nullable', 'required_without:name_en', 'string', 'max:150'],
            'name_en' => ['nullable', 'required_without:name_ar', 'string', 'max:150'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'date_of_birth' => ['nullable', 'date', 'before:-14 years', 'after:1900-01-01'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'nationality' => ['nullable', 'string', 'size:2', 'alpha'],
            'city' => ['nullable', 'string', 'max:100'],
            'current_job_title' => ['nullable', 'string', 'max:150'],
            'current_employer' => ['nullable', 'string', 'max:150'],
            'experience_years' => ['nullable', 'decimal:0,1', 'min:0', 'max:60'],
            'qualifications' => ['nullable', 'string', 'max:5000'],
            'skills' => ['nullable', 'string', 'max:2000'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'expected_salary' => $salary ? ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'] : ['exclude'],
            'current_salary' => $salary ? ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'] : ['exclude'],
            'source' => ['required', Rule::enum(CandidateSource::class)],
            'source_detail' => ['nullable', 'string', 'max:150'],
            'referred_by_employee_id' => ['nullable', 'integer', new ExistsInScope(Employee::class, keep: $this->candidate()?->referred_by_employee_id, activeOnly: false)],
            'linkedin_url' => ['nullable', 'url:http,https', 'max:255'],
            'portfolio_url' => ['nullable', 'url:http,https', 'max:255'],
            'locale' => ['nullable', Rule::in(['ar', 'en'])],
            'cv' => ['nullable', 'file', 'mimes:'.$cvTypes, 'extensions:'.$cvTypes, 'max:'.(int) setting('recruitment.cv_max_size_kb')],
            'vacancy_id' => $creating
                ? [$this->user()->hasDataRestrictions() ? 'required' : 'nullable', 'integer', Rule::exists(Vacancy::class, 'id')->where('status', 'open'), new ExistsInScope(Vacancy::class, activeOnly: false)]
                : ['exclude'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->boolean('confirm_duplicate')) {
                    return;
                }

                $duplicates = app(CandidateDuplicates::class)->find($this->input('email'), $this->input('phone'), $this->candidate());

                if ($duplicates->isNotEmpty()) {
                    $validator->errors()->add('email', __('recruitment.validation.duplicate_candidate', [
                        'names' => $duplicates->map(fn (Candidate $candidate) => $candidate->name.' (#'.$candidate->getKey().')')->implode(', '),
                    ]));
                    session()->flash('duplicateCandidates', $duplicates->map->only(['id', 'name_ar', 'name_en', 'email', 'phone'])->all());
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('recruitment.fields');
    }
}
