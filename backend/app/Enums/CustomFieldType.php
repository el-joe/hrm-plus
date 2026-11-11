<?php

namespace App\Enums;

/**
 * Value type of an admin-defined employee custom field.
 */
enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';

    public function label(): string
    {
        return __('employees.enums.custom_field_types.'.$this->value);
    }

    /**
     * Column of employee_custom_field_values that holds this type.
     */
    public function valueColumn(): string
    {
        return match ($this) {
            self::Text, self::Textarea, self::Select => 'value_text',
            self::Number => 'value_number',
            self::Date => 'value_date',
            self::Boolean => 'value_boolean',
        };
    }
}
