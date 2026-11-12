<?php

namespace App\Enums;

/**
 * Type of an extra field a request category asks for.
 */
enum HrRequestFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Date = 'date';
    case Number = 'number';
    case Select = 'select';

    public function label(): string
    {
        return __('hr_requests.enums.field_types.'.$this->value);
    }

    /**
     * @return list<mixed>
     */
    public function rules(): array
    {
        return match ($this) {
            self::Text => ['string', 'max:255'],
            self::Textarea => ['string', 'max:5000'],
            self::Date => ['date'],
            self::Number => ['numeric', 'between:-999999999,999999999'],
            self::Select => ['string', 'max:255'],
        };
    }
}
