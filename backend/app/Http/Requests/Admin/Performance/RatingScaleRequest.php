<?php

namespace App\Http\Requests\Admin\Performance;

use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\RatingScale;
use App\Models\RatingScaleLevel;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Rating scale with its levels (rows without a value and labels are ignored).
 * At least two levels; values and thresholds unique; the lowest level starts at 0.
 */
class RatingScaleRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return RatingScale::class;
    }

    protected function routeKey(): string
    {
        return 'rating_scale';
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $levels = collect((array) $this->input('levels', []))
            ->filter(fn ($row): bool => is_array($row) && (filled($row['value'] ?? null) || filled($row['label_ar'] ?? null) || filled($row['label_en'] ?? null)))
            ->values()->all();

        $this->merge(['levels' => $levels]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'description' => ['nullable', 'string', 'max:1000'],
            'levels' => ['required', 'array', 'min:2', 'max:10'],
            'levels.*.value' => ['required', 'decimal:0,2', 'between:0,100', 'distinct'],
            'levels.*.label_ar' => ['required', 'string', 'max:100'],
            'levels.*.label_en' => ['required', 'string', 'max:100'],
            'levels.*.min_score' => ['required', 'decimal:0,2', 'between:0,100', 'distinct'],
            'levels.*.description' => ['nullable', 'string', 'max:500'],
            'levels.*.color' => ['required', Rule::in(RatingScaleLevel::COLORS)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $levels = collect($this->input('levels'))->sortBy(fn (array $row): float => (float) $row['value'])->values();

            if (bccomp((string) $levels->first()['min_score'], '0', 2) !== 0) {
                $validator->errors()->add('levels', __('performance.validation.lowest_threshold'));
            }

            $previous = null;

            foreach ($levels as $row) {
                if ($previous !== null && bccomp((string) $row['min_score'], $previous, 2) <= 0) {
                    $validator->errors()->add('levels', __('performance.validation.thresholds_order'));

                    break;
                }

                $previous = (string) $row['min_score'];
            }
        }];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return [
            'code' => $this->validated('code'), 'name_ar' => $this->validated('name_ar'), 'name_en' => $this->validated('name_en'),
            'description' => $this->validated('description'), 'is_active' => $this->boolean('is_active', true),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function levels(): array
    {
        return array_values($this->validated('levels'));
    }
}
