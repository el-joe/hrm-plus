<?php

namespace App\Http\Requests\Admin\Recruitment;

use App\Models\JobApplication;
use App\Models\RecruitmentStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveApplicationRequest extends FormRequest
{
    public function application(): JobApplication
    {
        return $this->route('application');
    }

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->application());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stage_id' => ['required', 'integer', Rule::exists(RecruitmentStage::class, 'id')],
            'note' => ['nullable', 'string', 'max:1000'],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function stage(): RecruitmentStage
    {
        return RecruitmentStage::query()->findOrFail($this->integer('stage_id'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('recruitment.fields');
    }
}
