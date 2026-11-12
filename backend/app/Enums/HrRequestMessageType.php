<?php

namespace App\Enums;

/**
 * reply: visible to the employee; internal_note: HR only — never shown in the portal, the API, a
 * notification or a letter; system: automatic thread entries visible to the employee.
 */
enum HrRequestMessageType: string
{
    case Reply = 'reply';
    case InternalNote = 'internal_note';
    case System = 'system';

    public function label(): string
    {
        return __('hr_requests.enums.message_types.'.$this->value);
    }

    public function isVisibleToEmployee(): bool
    {
        return $this !== self::InternalNote;
    }
}
