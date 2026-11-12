<?php

namespace App\Http\Requests\Admin\Training;

use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\TrainingProgramme;

class TrainingProgrammeRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return TrainingProgramme::class;
    }

    protected function routeKey(): string
    {
        return 'training_programme';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [...$this->identityRules(), 'description' => ['nullable', 'string', 'max:2000']];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_active']);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('training.fields');
    }
}
