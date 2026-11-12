<?php

namespace App\Http\Requests\Admin\Assets;

use App\Enums\AssetCondition;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\OnboardingTask;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Handover form. The asset and the employee are looked up inside the admin's
 * data scope, so records outside it are "not found" for validation.
 */
class AssignAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assets.update');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer'],
            'employee_id' => ['required', 'integer'],
            'assigned_on' => ['required', 'date', 'before_or_equal:today'],
            'expected_return_on' => ['nullable', 'date', 'after_or_equal:assigned_on'],
            'condition_on_issue' => ['required', Rule::enum(AssetCondition::class)],
            'handover_notes' => ['nullable', 'string', 'max:2000'],
            'onboarding_task_id' => ['nullable', 'integer'],
        ];
    }

    public function asset(): ?Asset
    {
        return Asset::query()->find($this->integer('asset_id'));
    }

    public function employee(): ?Employee
    {
        return Employee::query()->find($this->integer('employee_id'));
    }

    public function onboardingTask(): ?OnboardingTask
    {
        return $this->filled('onboarding_task_id') ? OnboardingTask::query()->find($this->integer('onboarding_task_id')) : null;
    }

    /**
     * @return array{assigned_on: string, expected_return_on: ?string, condition_on_issue: string, handover_notes: ?string}
     */
    public function handover(): array
    {
        return [
            'assigned_on' => $this->string('assigned_on')->toString(),
            'expected_return_on' => $this->input('expected_return_on') ?: null,
            'condition_on_issue' => $this->string('condition_on_issue')->toString(),
            'handover_notes' => $this->input('handover_notes') ?: null,
        ];
    }
}
