<?php

namespace App\Http\Requests\Admin\Training;

use App\Enums\TrainingDeliveryMode;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\Skill;
use App\Models\TrainingCourse;
use App\Models\TrainingProgramme;
use App\Models\TrainingProvider;
use Illuminate\Validation\Rule;

class TrainingCourseRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return TrainingCourse::class;
    }

    protected function routeKey(): string
    {
        return 'training_course';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'training_programme_id' => ['nullable', 'integer', $this->inScope(TrainingProgramme::class, 'training_programme_id')],
            'training_provider_id' => ['nullable', 'integer', $this->inScope(TrainingProvider::class, 'training_provider_id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'delivery_mode' => ['required', Rule::enum(TrainingDeliveryMode::class)],
            'duration_hours' => ['required', 'decimal:0,2', 'min:0', 'max:9999'],
            'cost_per_participant' => ['required', 'decimal:0,2', 'min:0', 'max:99999999'],
            'issues_certificate' => ['sometimes', 'boolean'],
            'certificate_validity_months' => ['nullable', 'integer', 'between:1,240'],
            'skills' => ['nullable', 'array', 'max:30'],
            'skills.*.skill_id' => ['nullable', 'integer', 'distinct', Rule::exists(Skill::class, 'id')],
            'skills.*.level' => ['nullable', 'integer', 'between:1,'.Skill::MAX_LEVEL, 'required_with:skills.*.skill_id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        $data = $this->withBooleans(['issues_certificate', 'is_active']);
        unset($data['skills']);
        $data['issues_certificate'] = (bool) ($data['issues_certificate'] ?? false);

        return $data;
    }

    /**
     * Skill id => level of the filled rows.
     *
     * @return array<int, int>
     */
    public function skills(): array
    {
        $skills = [];

        foreach ((array) $this->validated('skills', []) as $row) {
            if (filled($row['skill_id'] ?? null)) {
                $skills[(int) $row['skill_id']] = (int) $row['level'];
            }
        }

        return $skills;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('training.fields');
    }
}
