<?php

namespace App\Http\Requests\HrRequests;

use App\Models\Employee;
use App\Models\HrRequestCategory;
use App\Services\HrRequests\HrRequestFileStore;
use App\Services\HrRequests\ProfileChangeFields;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Shared validation of a new HR request (portal, API, admin "on behalf"). Category fields are checked by
 * App\Actions\HrRequests\SubmitHrRequest; profile changes use the admin employee form rules.
 */
trait HrRequestRules
{
    public function category(): ?HrRequestCategory
    {
        $id = $this->input('hr_request_category_id');

        return is_numeric($id) ? HrRequestCategory::query()->active()->with('fields')->find((int) $id) : null;
    }

    /**
     * @param  list<string>  $priorities
     * @return array<string, mixed>
     */
    protected function requestRules(?Employee $employee, array $priorities): array
    {
        $category = $this->category();

        $rules = [
            'hr_request_category_id' => ['required', 'integer', Rule::exists('hr_request_categories', 'id')->where('is_active', true)],
            'subject' => ['nullable', 'string', 'max:200'],
            'description' => [$category !== null && ! $category->isLetter() && ! $category->isProfileChange() ? 'required' : 'nullable', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in($priorities)],
            'letter_language' => ['nullable', Rule::in(['ar', 'en'])],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['nullable'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => HrRequestFileStore::fileRules(),
        ];

        if ($category?->isProfileChange() && $employee !== null) {
            $rules += app(ProfileChangeFields::class)->rules($employee);
        }

        return $rules;
    }

    protected function prepareRequestInput(): void
    {
        $changes = $this->input('changes');

        if (is_array($changes)) {
            // Empty inputs of the change form mean "no change".
            $this->merge(['changes' => array_filter($changes, fn ($value): bool => filled($value))]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...trans('hr_requests.fields'), ...app(ProfileChangeFields::class)->attributes()];
    }

    /**
     * @return list<UploadedFile>
     */
    public function attachmentFiles(): array
    {
        return array_values(array_filter((array) $this->file('attachments', []), fn ($file): bool => $file instanceof UploadedFile));
    }

    /**
     * @return array{subject?: ?string, description?: ?string, priority?: ?string, letter_language?: ?string, fields?: array<string, mixed>, changes?: array<string, mixed>}
     */
    public function requestData(): array
    {
        return [
            'subject' => $this->validated('subject'),
            'description' => $this->validated('description'),
            'priority' => $this->validated('priority'),
            'letter_language' => $this->validated('letter_language'),
            'fields' => (array) $this->input('fields', []),
            'changes' => (array) $this->validated('changes', []),
        ];
    }
}
