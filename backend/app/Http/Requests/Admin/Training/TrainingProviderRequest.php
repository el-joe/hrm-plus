<?php

namespace App\Http\Requests\Admin\Training;

use App\Enums\TrainingProviderType;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\TrainingProvider;
use Illuminate\Validation\Rule;

class TrainingProviderRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return TrainingProvider::class;
    }

    protected function routeKey(): string
    {
        return 'training_provider';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'type' => ['required', Rule::enum(TrainingProviderType::class)],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url', 'max:255'],
        ];
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
