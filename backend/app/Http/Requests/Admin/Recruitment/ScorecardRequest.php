<?php

namespace App\Http\Requests\Admin\Recruitment;

use App\Enums\ScorecardRecommendation;
use App\Models\Interview;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An interviewer's scorecard (admin area and portal). Every criterion of the
 * interview's evaluation form must be scored 1..score_scale.
 */
class ScorecardRequest extends FormRequest
{
    public function interview(): Interview
    {
        return $this->route('interview') ?? $this->route('assignedInterview');
    }

    public function authorize(): bool
    {
        return $this->user()->can('score', $this->interview());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'recommendation' => ['required', Rule::enum(ScorecardRecommendation::class)],
            'comments' => ['nullable', 'string', 'max:5000'],
            'scores' => ['array'],
            'comments_by_criterion' => ['array'],
            'comments_by_criterion.*' => ['nullable', 'string', 'max:500'],
        ];

        foreach ($this->interview()->evaluationForm?->criteria ?? [] as $criterion) {
            $rules['scores.'.$criterion->getKey()] = ['required', 'integer', 'between:1,'.config('recruitment.score_scale')];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = trans('recruitment.fields');

        foreach ($this->interview()->evaluationForm?->criteria ?? [] as $criterion) {
            $attributes['scores.'.$criterion->getKey()] = $criterion->name;
        }

        return $attributes;
    }
}
