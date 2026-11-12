<?php

namespace App\Enums;

/**
 * What an announcement target points at. A department target includes its sub-departments.
 */
enum AnnouncementTargetType: string
{
    case Branch = 'branch';
    case Department = 'department';
    case EmployeeGroup = 'employee_group';
    case EmploymentType = 'employment_type';

    public function label(): string
    {
        return __('announcements.enums.target_types.'.$this->value);
    }

    /**
     * The admin data scope dimension restricting this target, if any.
     */
    public function scopeType(): ?DataScopeType
    {
        return match ($this) {
            self::Branch => DataScopeType::Branch,
            self::Department => DataScopeType::Department,
            self::EmployeeGroup => DataScopeType::EmployeeGroup,
            self::EmploymentType => null,
        };
    }
}
