<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Enums\AttendanceStatusCategory;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\AttendanceStatus;
use Illuminate\Validation\Rule;

class AttendanceStatusRequest extends StructureRequest
{
    protected function modelClass(): string
    {
        return AttendanceStatus::class;
    }

    protected function routeKey(): string
    {
        return 'attendance_status';
    }

    protected function prepareForValidation(): void
    {
        $record = $this->record();

        // The code of a system status never changes.
        $this->merge(['code' => $record?->is_system ? $record->code : mb_strtolower(trim((string) $this->input('code')))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('attendance_statuses', 'code')->ignore($this->record())],
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::enum(AttendanceStatusCategory::class)],
            'counts_as_worked' => ['sometimes', 'boolean'],
            'is_paid' => ['sometimes', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordData(): array
    {
        return $this->withBooleans(['counts_as_worked', 'is_paid', 'is_active']);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('schedules.fields');
    }
}
