<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Enums\BreakType;
use App\Enums\ShiftType;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\Shift;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ShiftRequest extends StructureRequest
{
    /**
     * Break rows offered by the form (empty rows are ignored).
     */
    public const MAX_BREAKS = 6;

    protected function modelClass(): string
    {
        return Shift::class;
    }

    protected function routeKey(): string
    {
        return 'shift';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $flexible = $this->input('type') === ShiftType::Flexible->value;

        return [
            ...$this->identityRules(),
            'type' => ['required', Rule::enum(ShiftType::class)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'required_minutes' => [$flexible ? 'required' : 'nullable', 'integer', 'between:1,1440'],
            'core_start_time' => ['nullable', 'date_format:H:i', 'required_with:core_end_time'],
            'core_end_time' => ['nullable', 'date_format:H:i', 'required_with:core_start_time'],
            'checkin_opens_before_minutes' => ['required', 'integer', 'between:0,720'],
            'checkout_closes_after_minutes' => ['required', 'integer', 'between:0,720'],
            'late_grace_minutes' => ['nullable', 'integer', 'between:0,240'],
            'early_leave_grace_minutes' => ['nullable', 'integer', 'between:0,240'],
            'is_night' => ['sometimes', 'boolean'],
            'color' => ['required', Rule::in(Shift::COLORS)],
            'description' => ['nullable', 'string', 'max:1000'],
            'breaks' => ['nullable', 'array', 'max:'.self::MAX_BREAKS],
            'breaks.*.name' => ['nullable', 'string', 'max:100'],
            'breaks.*.type' => ['nullable', Rule::enum(BreakType::class)],
            'breaks.*.start_time' => ['nullable', 'date_format:H:i'],
            'breaks.*.end_time' => ['nullable', 'date_format:H:i'],
            'breaks.*.duration_minutes' => ['nullable', 'integer', 'between:1,480'],
            'breaks.*.is_paid' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->breakRows() as $index => $row) {
                if ($row['type'] === BreakType::Fixed->value && (! $row['start_time'] || ! $row['end_time'])) {
                    $validator->errors()->add("breaks.{$index}.start_time", __('validation.required', ['attribute' => __('schedules.fields.break_start')]));
                }

                if ($row['type'] === BreakType::Flexible->value && ! $row['duration_minutes']) {
                    $validator->errors()->add("breaks.{$index}.duration_minutes", __('validation.required', ['attribute' => __('schedules.fields.duration_minutes')]));
                }
            }
        }];
    }

    /**
     * Shift attributes (breaks excluded); flexible-only fields cleared for fixed shifts.
     *
     * @return array<string, mixed>
     */
    public function shiftData(): array
    {
        $data = collect($this->withBooleans(['is_active', 'is_night']))->except('breaks')->all();

        if ($data['type'] !== ShiftType::Flexible->value) {
            $data['required_minutes'] = $data['core_start_time'] = $data['core_end_time'] = null;
        }

        return $data;
    }

    /**
     * Non-empty break rows; a fixed break's duration is derived from its times.
     *
     * @return array<int, array{name: ?string, type: string, start_time: ?string, end_time: ?string, duration_minutes: ?int, is_paid: bool, sort_order: int}>
     */
    public function breakRows(): array
    {
        $rows = [];

        foreach ((array) $this->input('breaks', []) as $index => $row) {
            $row = (array) $row;
            $type = $row['type'] ?? null;

            if (blank($row['start_time'] ?? null) && blank($row['duration_minutes'] ?? null) && blank($row['name'] ?? null)) {
                continue;
            }

            $type = in_array($type, [BreakType::Fixed->value, BreakType::Flexible->value], true) ? $type : BreakType::Fixed->value;
            $start = $row['start_time'] ?? null ?: null;
            $end = $row['end_time'] ?? null ?: null;
            $duration = filled($row['duration_minutes'] ?? null) ? (int) $row['duration_minutes'] : null;

            if ($type === BreakType::Fixed->value && $start && $end) {
                $duration = (Shift::minuteOfDay($end) - Shift::minuteOfDay($start) + 1440) % 1440 ?: null;
            }

            $rows[$index] = [
                'name' => $row['name'] ?? null ?: null,
                'type' => $type,
                'start_time' => $type === BreakType::Fixed->value ? $start : null,
                'end_time' => $type === BreakType::Fixed->value ? $end : null,
                'duration_minutes' => $duration,
                'is_paid' => filter_var($row['is_paid'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => count($rows),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('schedules.fields');
    }
}
