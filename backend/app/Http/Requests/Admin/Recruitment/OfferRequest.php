<?php

namespace App\Http\Requests\Admin\Recruitment;

use App\Models\EmploymentType;
use App\Models\JobApplication;
use App\Models\JobOffer;
use App\Models\JobTitle;
use App\Models\Position;
use App\Rules\ExistsInScope;
use App\Services\Recruitment\SalaryVisibility;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create / edit an offer. The salary is accepted only from admins who may
 * see salaries; without it the offer stays a draft for someone who can.
 */
class OfferRequest extends FormRequest
{
    public function offer(): ?JobOffer
    {
        $offer = $this->route('offer');

        return $offer instanceof JobOffer ? $offer : null;
    }

    public function authorize(): bool
    {
        if ($this->offer()) {
            return $this->user()->can('update', $this->offer());
        }

        $application = $this->route('application');

        return $application instanceof JobApplication && $this->user()->can('create', JobOffer::class) && $application->isVisibleTo($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $offer = $this->offer();

        return [
            'position_id' => ['nullable', 'integer', new ExistsInScope(Position::class, keep: $offer?->position_id)],
            'job_title_id' => ['nullable', 'integer', new ExistsInScope(JobTitle::class, keep: $offer?->job_title_id)],
            'employment_type_id' => ['nullable', 'integer', new ExistsInScope(EmploymentType::class, keep: $offer?->employment_type_id)],
            'basic_salary' => app(SalaryVisibility::class)->allows($this->user())
                ? ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99']
                : ['exclude'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'expires_on' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:start_date'],
            'probation_months' => ['nullable', 'integer', 'between:0,12'],
            'benefits' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:10000'],
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
