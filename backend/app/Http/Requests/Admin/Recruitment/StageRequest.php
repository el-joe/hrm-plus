<?php

namespace App\Http\Requests\Admin\Recruitment;

use App\Enums\StageCategory;
use App\Models\RecruitmentStage;
use App\Models\Vacancy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A pipeline stage (company default, or of a vacancy with its own pipeline).
 * Codes are unique within their pipeline.
 */
class StageRequest extends FormRequest
{
    public function stage(): ?RecruitmentStage
    {
        $stage = $this->route('stage');

        return $stage instanceof RecruitmentStage ? $stage : null;
    }

    public function vacancyId(): ?int
    {
        return $this->stage() ? $this->stage()->vacancy_id : ($this->filled('vacancy_id') ? $this->integer('vacancy_id') : null);
    }

    public function authorize(): bool
    {
        if (! $this->user()->can('recruitment.manage')) {
            return false;
        }

        $vacancyId = $this->vacancyId();

        return $vacancyId === null || Vacancy::query()->whereKey($vacancyId)->where('uses_custom_pipeline', true)->exists();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtolower(trim($this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $vacancyId = $this->vacancyId();

        return [
            'vacancy_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('recruitment_stages', 'code')->ignore($this->stage())
                    ->where(fn ($query) => $vacancyId === null ? $query->whereNull('vacancy_id') : $query->where('vacancy_id', $vacancyId))],
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::enum(StageCategory::class)],
            'notify_candidate' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
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
