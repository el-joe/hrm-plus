<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ratings of a review: self-assessment (portal / API) or manager assessment (admin).
 * Ratings must be values of the cycle's scale — checked by ManagePerformanceReview,
 * which knows the scale. Access is decided by the controller (policy / ownership).
 *
 * goals[{goal_id}][rating|comment], competencies[{review_competency_id}][rating|comment],
 * comments, strengths, improvements (manager only).
 */
class ReviewRatingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'goals' => ['nullable', 'array'],
            'goals.*.rating' => ['nullable', 'numeric', 'between:0,100'],
            'goals.*.comment' => ['nullable', 'string', 'max:2000'],
            'competencies' => ['nullable', 'array'],
            'competencies.*.rating' => ['nullable', 'numeric', 'between:0,100'],
            'competencies.*.comment' => ['nullable', 'string', 'max:2000'],
            'comments' => ['nullable', 'string', 'max:5000'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'improvements' => ['nullable', 'string', 'max:5000'],
            'submit' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{goals: array<int|string, array<string, mixed>>, competencies: array<int|string, array<string, mixed>>, comments: ?string, strengths: ?string, improvements: ?string}
     */
    public function ratings(): array
    {
        return [
            'goals' => (array) $this->input('goals', []),
            'competencies' => (array) $this->input('competencies', []),
            'comments' => $this->input('comments'),
            'strengths' => $this->input('strengths'),
            'improvements' => $this->input('improvements'),
        ];
    }
}
