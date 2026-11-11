<?php

namespace App\Http\Requests\Admin\Scheduling;

use App\Enums\ScheduleType;
use App\Http\Requests\Admin\Structure\StructureRequest;
use App\Models\Shift;
use App\Models\WorkSchedule;
use Illuminate\Validation\Rule;

class WorkScheduleRequest extends StructureRequest
{
    public const MAX_CYCLE_DAYS = 31;

    protected function modelClass(): string
    {
        return WorkSchedule::class;
    }

    protected function routeKey(): string
    {
        return 'work_schedule';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rotating = $this->input('type') === ScheduleType::Rotating->value;

        return [
            ...$this->identityRules(),
            'type' => ['required', Rule::enum(ScheduleType::class)],
            'cycle_length_days' => [$rotating ? 'required' : 'nullable', 'integer', 'between:2,'.self::MAX_CYCLE_DAYS],
            'cycle_start_date' => [$rotating ? 'required' : 'nullable', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pattern' => ['nullable', 'array'],
            'pattern.*' => ['nullable', 'array'],
            'pattern.*.*' => ['nullable', 'array'],
            'pattern.*.*.*' => ['integer', Rule::exists(Shift::class, 'id')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function scheduleData(): array
    {
        $data = collect($this->withBooleans(['is_active']))->except('pattern')->all();

        if ($data['type'] !== ScheduleType::Rotating->value) {
            $data['cycle_length_days'] = $data['cycle_start_date'] = null;
        }

        return $data;
    }

    /**
     * Shift ids per pattern day of the chosen type, limited to the pattern length.
     *
     * @return array<int, list<int>>
     */
    public function patternDays(): array
    {
        $type = ScheduleType::from($this->input('type'));
        $indexes = match ($type) {
            ScheduleType::Weekly => range(0, 6),
            ScheduleType::Rotating => range(0, (int) $this->input('cycle_length_days') - 1),
            ScheduleType::Monthly => range(1, 31),
        };
        $submitted = (array) $this->input('pattern.'.$type->value, []);
        $days = [];

        foreach ($indexes as $index) {
            $days[$index] = array_values(array_unique(array_map('intval', (array) ($submitted[$index] ?? []))));
        }

        return $days;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return trans('schedules.fields');
    }
}
