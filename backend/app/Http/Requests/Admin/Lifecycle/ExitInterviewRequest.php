<?php

namespace App\Http\Requests\Admin\Lifecycle;

use App\Models\OffboardingCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Exit interview answers, validated against config('lifecycle.exit_interview').
 */
class ExitInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $case = $this->route('offboarding');

        return $case instanceof OffboardingCase && $this->user()->can('update', $case);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ['notes' => ['nullable', 'string', 'max:5000'], 'answers' => ['array']];

        foreach (config('lifecycle.exit_interview') as $key => $question) {
            $rules['answers.'.$key] = match ($question['type']) {
                'rating' => ['nullable', 'integer', 'between:1,5'],
                'boolean' => ['nullable', 'boolean'],
                'choice' => ['nullable', Rule::in($question['options'])],
            };
        }

        return $rules;
    }

    /**
     * @return array<string, mixed>
     */
    public function answers(): array
    {
        return array_filter($this->validated('answers', []), fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(config('lifecycle.exit_interview'))->keys()
            ->mapWithKeys(fn (string $key) => ['answers.'.$key => __('onboarding.exit.questions.'.$key)])->all();
    }
}
