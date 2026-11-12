<?php

namespace App\Http\Requests\Admin\HrRequests;

use App\Models\HrLetterTemplate;
use App\Services\HrRequests\HrLetterDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HrLetterTemplateRequest extends FormRequest
{
    public function template(): ?HrLetterTemplate
    {
        $template = $this->route('hr_letter_template');

        return $template instanceof HrLetterTemplate ? $template : null;
    }

    public function authorize(): bool
    {
        $template = $this->template();

        return $template ? $this->user()->can('update', $template) : $this->user()->can('create', HrLetterTemplate::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? mb_strtoupper(trim($this->input('code'))) : $this->input('code'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('hr_letter_templates', 'code')->ignore($this->template())],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'body_ar' => ['required', 'string', 'max:10000'],
            'body_en' => ['required', 'string', 'max:10000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return list<\Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $document = app(HrLetterDocument::class);

            foreach (['body_ar', 'body_en'] as $field) {
                $unknown = $document->unknownPlaceholders((string) $this->input($field));

                if ($unknown !== []) {
                    $validator->errors()->add($field, __('hr_requests.letters.unknown_placeholders', [
                        'names' => implode(', ', array_map(fn (string $name): string => '{{'.$name.'}}', $unknown)),
                    ]));
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('hr_requests.letter_fields');
    }
}
