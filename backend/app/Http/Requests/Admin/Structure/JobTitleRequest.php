<?php

namespace App\Http\Requests\Admin\Structure;

use App\Models\JobTitle;

class JobTitleRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return JobTitle::class;
    }

    protected function routeKey(): string
    {
        return 'job_title';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_active']);
    }
}
