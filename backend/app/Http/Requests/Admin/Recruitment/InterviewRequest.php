<?php

namespace App\Http\Requests\Admin\Recruitment;

use App\Enums\InterviewMode;
use App\Enums\InterviewType;
use App\Enums\UserStatus;
use App\Models\EvaluationForm;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\User;
use App\Rules\ExistsInScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Schedule / reschedule an interview. Date and time are in the given
 * timezone; online interviews need a meeting link, in-person ones a place.
 * Interviewers are active admin users or employee accounts.
 */
class InterviewRequest extends FormRequest
{
    public function interview(): ?Interview
    {
        $interview = $this->route('interview');

        return $interview instanceof Interview ? $interview : null;
    }

    public function authorize(): bool
    {
        if ($this->interview()) {
            return $this->user()->can('update', $this->interview());
        }

        $application = $this->route('application');

        return $application instanceof JobApplication
            && $this->user()->can('create', Interview::class)
            && $application->isVisibleTo($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(InterviewType::class)],
            'date' => ['required', 'date_format:Y-m-d', $this->interview() ? 'after_or_equal:-1 year' : 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone:all'],
            'duration_minutes' => ['required', 'integer', 'between:10,480'],
            'mode' => ['required', Rule::enum(InterviewMode::class)],
            'location' => ['nullable', Rule::requiredIf($this->input('mode') === InterviewMode::InPerson->value), 'string', 'max:255'],
            'meeting_url' => ['nullable', Rule::requiredIf($this->input('mode') === InterviewMode::Online->value), 'url:https,http', 'max:500'],
            'evaluation_form_id' => ['nullable', 'integer', new ExistsInScope(EvaluationForm::class, keep: $this->interview()?->evaluation_form_id)],
            'interviewer_ids' => ['required', 'array', 'min:1', 'max:10'],
            'interviewer_ids.*' => ['integer', 'distinct', Rule::exists(User::class, 'id')->where('status', UserStatus::Active->value)->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:2000'],
            'notify_candidate' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [...$this->validated(), 'notify_candidate' => $this->has('notify_candidate') ? $this->boolean('notify_candidate') : true];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [...trans('recruitment.fields'), 'interviewer_ids' => __('recruitment.fields.interviewers'), 'interviewer_ids.*' => __('recruitment.fields.interviewers')];
    }
}
