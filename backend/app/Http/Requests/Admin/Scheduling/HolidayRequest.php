<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\Branch;
use App\Models\Holiday;

class HolidayRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return Holiday::class;
    }

    protected function routeKey(): string
    {
        return 'holiday';
    }

    protected function prepareForValidation(): void
    {
        if (blank($this->input('end_date')) && filled($this->input('start_date'))) {
            $this->merge(['end_date' => $this->input('start_date')]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date', 'before_or_equal:'.$this->input('start_date').' +1 year'],
            'is_recurring' => ['sometimes', 'boolean'],
            'is_paid' => ['sometimes', 'boolean'],
            'branch_id' => ['nullable', 'integer', $this->inScope(Branch::class, 'branch_id')],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['is_recurring', 'is_paid', 'is_active']);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('schedules.fields');
    }
}
