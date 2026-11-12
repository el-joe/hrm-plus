<?php

namespace App\Http\Requests\Admin\Performance;

use App\Enums\PerformanceCycleType;
use App\Models\Branch;
use App\Models\Competency;
use App\Models\Department;
use App\Models\PerformanceCycle;
use App\Models\RatingScale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Cycle form. Weight totals (split = 100, competency weights = 100) are checked by
 * ManagePerformanceCycle so the API and seeders get the same rule.
 */
class PerformanceCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cycle = $this->route('performance_cycle');

        return $cycle instanceof PerformanceCycle ? $this->user()->can('update', $cycle) : $this->user()->can('create', PerformanceCycle::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => mb_strtoupper(trim((string) $this->input('code')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cycle = $this->route('performance_cycle');

        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', Rule::unique('performance_cycles', 'code')->ignore($cycle)],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(PerformanceCycleType::class)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after:period_start'],
            'rating_scale_id' => ['required', 'integer', Rule::exists(RatingScale::class, 'id')->where('is_active', true)],
            'goals_weight' => ['required', 'decimal:0,2', 'between:0,100'],
            'competencies_weight' => ['required', 'decimal:0,2', 'between:0,100'],
            'self_assessment_enabled' => ['sometimes', 'boolean'],
            'self_assessment_due' => ['nullable', 'date'],
            'manager_assessment_due' => ['nullable', 'date', 'after_or_equal:self_assessment_due'],
            'branch_id' => ['nullable', 'integer', Rule::exists(Branch::class, 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists(Department::class, 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'competencies' => ['nullable', 'array', 'max:30'],
            'competencies.*.competency_id' => ['nullable', 'integer', Rule::exists(Competency::class, 'id')],
            'competencies.*.weight' => ['nullable', 'decimal:0,2', 'gt:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cycleData(): array
    {
        $data = $this->validated();
        $data['self_assessment_enabled'] = $this->boolean('self_assessment_enabled');
        $data['competencies'] = array_values($data['competencies'] ?? []);

        return $data;
    }
}
