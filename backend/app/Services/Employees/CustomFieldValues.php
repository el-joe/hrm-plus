<?php

namespace App\Services\Employees;

use App\Enums\CustomFieldType;
use App\Models\Employee;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeCustomFieldValue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Validation and storage of employee custom field values. Input arrives as
 * custom_fields[<code>] => value; each value is stored in the typed column
 * of its field (text, number, date, boolean).
 */
class CustomFieldValues
{
    /**
     * Active field definitions, in display order.
     *
     * @return Collection<int, EmployeeCustomField>
     */
    public function activeFields(): Collection
    {
        return EmployeeCustomField::query()->active()->with('options')->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = ['custom_fields' => ['sometimes', 'array']];

        foreach ($this->activeFields() as $field) {
            $rules['custom_fields.'.$field->code] = [
                $field->is_required ? 'required' : 'nullable',
                ...match ($field->type) {
                    CustomFieldType::Text => ['string', 'max:255'],
                    CustomFieldType::Textarea => ['string', 'max:2000'],
                    CustomFieldType::Number => ['numeric', 'between:-99999999999999,99999999999999'],
                    CustomFieldType::Date => ['date'],
                    CustomFieldType::Boolean => ['boolean'],
                    CustomFieldType::Select => [Rule::in($field->options->pluck('value')->all())],
                },
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->activeFields()
            ->mapWithKeys(fn (EmployeeCustomField $field): array => ['custom_fields.'.$field->code => $field->name])
            ->all();
    }

    /**
     * Save the submitted values (only fields present in $values are touched).
     *
     * @param  array<string, mixed>  $values  code => value
     */
    public function save(Employee $employee, array $values): void
    {
        foreach ($this->activeFields() as $field) {
            if (! array_key_exists($field->code, $values)) {
                continue;
            }

            $value = $values[$field->code];
            $record = EmployeeCustomFieldValue::query()->firstOrNew(['employee_id' => $employee->getKey(), 'custom_field_id' => $field->getKey()]);

            if ($value === null || $value === '') {
                if ($record->exists) {
                    $record->delete();
                }

                continue;
            }

            $record->fill([
                'value_text' => null, 'value_number' => null, 'value_date' => null, 'value_boolean' => null,
                $field->type->valueColumn() => match ($field->type) {
                    CustomFieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                    CustomFieldType::Date => Carbon::parse($value)->toDateString(),
                    CustomFieldType::Number => (string) $value,
                    default => (string) $value,
                },
            ])->save();
        }
    }

    /**
     * Display rows for a profile: [field, formatted value], optionally only those shown to the employee.
     *
     * @return list<array{field: EmployeeCustomField, value: ?string}>
     */
    public function display(Employee $employee, bool $employeeView = false): array
    {
        $values = $employee->customFieldValues()->get()->keyBy('custom_field_id');

        return $this->activeFields()
            ->filter(fn (EmployeeCustomField $field): bool => ! $employeeView || $field->visible_to_employee)
            ->map(function (EmployeeCustomField $field) use ($values): array {
                $value = $values->get($field->getKey());

                return ['field' => $field, 'value' => $value ? $this->format($field, $value) : null];
            })
            ->values()
            ->all();
    }

    /**
     * Raw values for a form, code => value.
     *
     * @return array<string, mixed>
     */
    public function formValues(Employee $employee): array
    {
        if (! $employee->exists) {
            return [];
        }

        $values = $employee->customFieldValues()->get()->keyBy('custom_field_id');

        return $this->activeFields()->mapWithKeys(function (EmployeeCustomField $field) use ($values): array {
            $value = $values->get($field->getKey())?->getAttribute($field->type->valueColumn());

            return [$field->code => match (true) {
                $value instanceof Carbon => $value->toDateString(),
                is_bool($value) => $value ? '1' : '0',
                default => $value,
            }];
        })->all();
    }

    private function format(EmployeeCustomField $field, EmployeeCustomFieldValue $value): ?string
    {
        $raw = $value->getAttribute($field->type->valueColumn());

        return match ($field->type) {
            CustomFieldType::Boolean => $raw ? __('app.common.yes') : __('app.common.no'),
            CustomFieldType::Date => $raw?->toDateString(),
            CustomFieldType::Number => $raw === null ? null : rtrim(rtrim((string) $raw, '0'), '.'),
            CustomFieldType::Select => $field->options->firstWhere('value', $raw)?->label() ?? $raw,
            default => $raw,
        };
    }
}
