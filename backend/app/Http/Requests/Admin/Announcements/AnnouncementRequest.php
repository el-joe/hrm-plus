<?php

namespace App\Http\Requests\Admin\Announcements;

use App\Enums\AnnouncementTargetType;
use App\Enums\AnnouncementType;
use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Create / edit an announcement. "intent" = draft (save only) | publish (save and publish, or schedule when
 * publish_at is in the future). Scope rules (who may target what) are checked by ManageAnnouncement.
 */
class AnnouncementRequest extends FormRequest
{
    public function announcement(): ?Announcement
    {
        $announcement = $this->route('announcement');

        return $announcement instanceof Announcement ? $announcement : null;
    }

    public function authorize(): bool
    {
        $announcement = $this->announcement();

        return $announcement ? $this->user()->can('update', $announcement) : $this->user()->can('create', Announcement::class);
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['is_pinned', 'requires_acknowledgement', 'notify_email', 'notify_push'] as $flag) {
            $merge[$flag] = $this->boolean($flag);
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $extensions = implode(',', (array) setting('documents.allowed_extensions'));
        $tables = [
            AnnouncementTargetType::Branch->value => 'branches',
            AnnouncementTargetType::Department->value => 'departments',
            AnnouncementTargetType::EmployeeGroup->value => 'employee_groups',
            AnnouncementTargetType::EmploymentType->value => 'employment_types',
        ];
        $rules = [
            'title_ar' => ['nullable', 'string', 'max:200', 'required_without:title_en'],
            'title_en' => ['nullable', 'string', 'max:200'],
            'body_ar' => ['nullable', 'string', 'max:50000', 'required_with:title_ar'],
            'body_en' => ['nullable', 'string', 'max:50000', 'required_with:title_en'],
            'type' => ['required', Rule::enum(AnnouncementType::class)],
            'audience' => ['required', Rule::in([Announcement::AUDIENCE_ALL, Announcement::AUDIENCE_TARGETED])],
            'targets' => ['nullable', 'array'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:now', ...(filled($this->input('publish_at')) ? ['after:publish_at'] : [])],
            'is_pinned' => ['boolean'],
            'requires_acknowledgement' => ['boolean'],
            'notify_email' => ['boolean'],
            'notify_push' => ['boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:'.$extensions, 'extensions:'.$extensions, 'max:'.(int) setting('announcements.attachment_max_kb')],
            'intent' => ['nullable', Rule::in(['draft', 'publish'])],
        ];

        foreach ($tables as $type => $table) {
            $rules['targets.'.$type] = ['nullable', 'array'];
            $rules['targets.'.$type.'.*'] = ['integer', Rule::exists($table, 'id')];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...trans('announcements.fields'),
            'attachments.*' => __('announcements.fields.attachments'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function announcementData(): array
    {
        return $this->safe()->except(['attachments', 'intent']);
    }

    /**
     * @return list<UploadedFile>
     */
    public function attachmentFiles(): array
    {
        return array_values(array_filter((array) $this->file('attachments', []), fn ($file): bool => $file instanceof UploadedFile));
    }

    public function wantsPublish(): bool
    {
        return $this->input('intent') === 'publish';
    }
}
