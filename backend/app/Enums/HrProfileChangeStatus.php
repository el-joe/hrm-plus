<?php

namespace App\Enums;

enum HrProfileChangeStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case Rejected = 'rejected';

    public function label(): string
    {
        return __('hr_requests.enums.change_statuses.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Applied => 'success',
            self::Rejected => 'danger',
        };
    }
}
