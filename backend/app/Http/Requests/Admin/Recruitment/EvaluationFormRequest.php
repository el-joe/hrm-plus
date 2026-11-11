<?php

namespace App\Http\Requests\Admin\Recruitment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * An evaluation form with its criteria rows. Empty rows (no name in either
 * language) are ignored; a row with an id updates that criterion.
 */
class EvaluationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recruitment.manage');
    }

    protected function prepareForValidation(): void
    {
        $criteria = collect($this->input('criteria', []))
            ->filter(fn ($row) => is_array($row) && (filled($row['name_ar'] ?? null) || filled($row['name_en'] ?? null)))
            ->values()->all();

        $this->merge(['criteria' => $criteria]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'criteria' => ['required', 'array', 'min:1', 'max:20'],
            'criteria.*.id' => ['nullable', 'integer'],
            'criteria.*.name_ar' => ['required', 'string', 'max:150'],
            'criteria.*.name_en' => ['required', 'string', 'max:150'],
            'criteria.*.description' => ['nullable', 'string', 'max:500'],
            'criteria.*.weight' => ['required', 'integer', 'between:1,10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...trans('recruitment.fields'),
            'criteria' => __('recruitment.fields.criteria'),
            'criteria.*.name_ar' => __('recruitment.fields.name_ar'),
            'criteria.*.name_en' => __('recruitment.fields.name_en'),
            'criteria.*.weight' => __('recruitment.fields.weight'),
        ];
    }
}
