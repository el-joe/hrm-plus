<?php

namespace App\Http\Requests\Admin\Recruitment;

use App\Enums\VacancyStatus;
use App\Models\Branch;
use App\Models\Department;
use App\Models\EmploymentType;
use App\Models\EvaluationForm;
use App\Models\JobTitle;
use App\Models\Position;
use App\Models\User;
use App\Models\Vacancy;
use App\Rules\ExistsInScope;
use App\Services\Recruitment\SalaryVisibility;
use App\Services\Recruitment\VacancyCapacity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / edit a vacancy. The department must belong to the branch (or be
 * company-wide), the position to the department, and the openings may not
 * exceed the position's free seats (VacancyCapacity). The salary range is
 * accepted only from admins who may see salaries.
 */
class VacancyRequest extends FormRequest
{
    public function vacancy(): ?Vacancy
    {
        $vacancy = $this->route('vacancy');

        return $vacancy instanceof Vacancy ? $vacancy : null;
    }

    public function authorize(): bool
    {
        return $this->vacancy() ? $this->user()->can('update', $this->vacancy()) : $this->user()->can('create', Vacancy::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vacancy = $this->vacancy();
        $salary = app(SalaryVisibility::class)->allows($this->user());

        return [
            'title_ar' => ['required', 'string', 'max:150'],
            'title_en' => ['required', 'string', 'max:150'],
            'description_ar' => ['nullable', 'string', 'max:10000'],
            'description_en' => ['nullable', 'string', 'max:10000'],
            'requirements_ar' => ['nullable', 'string', 'max:10000'],
            'requirements_en' => ['nullable', 'string', 'max:10000'],
            'branch_id' => ['required', 'integer', new ExistsInScope(Branch::class, keep: $vacancy?->branch_id)],
            'department_id' => ['required', 'integer', new ExistsInScope(Department::class, keep: $vacancy?->department_id)],
            'position_id' => ['nullable', 'integer', new ExistsInScope(Position::class, keep: $vacancy?->position_id)],
            'job_title_id' => ['nullable', 'integer', new ExistsInScope(JobTitle::class, keep: $vacancy?->job_title_id)],
            'employment_type_id' => ['nullable', 'integer', new ExistsInScope(EmploymentType::class, keep: $vacancy?->employment_type_id)],
            'openings' => ['required', 'integer', 'min:'.max(1, (int) $vacancy?->filled_count), 'max:500'],
            'salary_min' => $salary ? ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99'] : ['exclude'],
            'salary_max' => $salary ? ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999.99', 'gte:salary_min'] : ['exclude'],
            'status' => ['required', Rule::enum(VacancyStatus::class)],
            'is_public' => ['sometimes', 'boolean'],
            'publish_at' => ['nullable', 'date'],
            'application_deadline' => ['nullable', 'date', 'after_or_equal:publish_at'],
            'evaluation_form_id' => ['nullable', 'integer', new ExistsInScope(EvaluationForm::class, keep: $vacancy?->evaluation_form_id)],
            'hiring_manager_id' => ['nullable', 'integer', Rule::exists(User::class, 'id')->where('account_type', 'admin')->whereNull('deleted_at')],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $department = Department::query()->withoutGlobalScopes()->find($this->integer('department_id'));

                if ($department?->branch_id !== null && (int) $department->branch_id !== $this->integer('branch_id')) {
                    $validator->errors()->add('department_id', __('employees.validation.department_not_in_branch'));
                }

                if ($this->filled('position_id')) {
                    $position = Position::query()->withoutGlobalScopes()->find($this->integer('position_id'));

                    if ((int) $position->department_id !== $this->integer('department_id')) {
                        $validator->errors()->add('position_id', __('employees.validation.position_not_in_department'));
                    } elseif (in_array($this->input('status'), [VacancyStatus::Draft->value, VacancyStatus::Open->value, VacancyStatus::OnHold->value], true)) {
                        $free = app(VacancyCapacity::class)->freeSeats($position, $this->vacancy());
                        $remaining = $this->integer('openings') - (int) $this->vacancy()?->filled_count;

                        if ($remaining > $free) {
                            $validator->errors()->add('openings', __('recruitment.validation.openings_exceed_seats', ['free' => $free, 'position' => $position->name]));
                        }
                    }
                }
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated($key, $default);

        if ($key === null) {
            $data['is_public'] = $this->boolean('is_public');
        }

        return $data;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('recruitment.fields');
    }
}
