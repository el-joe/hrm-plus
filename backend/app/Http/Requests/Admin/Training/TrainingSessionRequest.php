<?php

namespace App\Http\Requests\Admin\Training;

use App\Enums\TrainingDeliveryMode;
use App\Models\Branch;
use App\Models\TrainingCourse;
use App\Models\TrainingSession;
use App\Rules\ExistsInScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrainingSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('training_session');

        return $session instanceof TrainingSession ? $this->user()->can('update', $session) : $this->user()->can('create', TrainingSession::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $session = $this->route('training_session');

        return [
            'training_course_id' => ['required', 'integer', new ExistsInScope(TrainingCourse::class, keep: $session?->training_course_id)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.$this->dateLimit()],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'delivery_mode' => ['required', Rule::enum(TrainingDeliveryMode::class)],
            'location' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn (): bool => $this->input('delivery_mode') !== TrainingDeliveryMode::Online->value)],
            'meeting_url' => ['nullable', 'url', 'max:500', Rule::requiredIf(fn (): bool => $this->input('delivery_mode') === TrainingDeliveryMode::Online->value)],
            'capacity' => ['required', 'integer', 'between:1,1000'],
            'trainer' => ['nullable', 'string', 'max:150'],
            'cost_per_participant' => ['nullable', 'decimal:0,2', 'min:0', 'max:99999999'],
            'branch_id' => ['nullable', 'integer', new ExistsInScope(Branch::class, keep: $session?->branch_id)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * A session lasts at most 90 days.
     */
    private function dateLimit(): string
    {
        $start = strtotime((string) $this->input('start_date')) ?: time();

        return date('Y-m-d', strtotime('+90 days', $start));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('training.fields');
    }
}
