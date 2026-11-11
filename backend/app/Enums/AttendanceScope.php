<?php

namespace App\Enums;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use Illuminate\Database\Eloquent\Model;

/**
 * What a schedule or attendance-policy assignment applies to. "company" has no
 * scope id. Precedence (most specific first) is defined per use:
 * schedules: employee > employee_group > department > branch > company;
 * policies:  employee > employee_group > employment_type > department > branch > company.
 */
enum AttendanceScope: string
{
    case Employee = 'employee';
    case EmployeeGroup = 'employee_group';
    case EmploymentType = 'employment_type';
    case Department = 'department';
    case Branch = 'branch';
    case Company = 'company';

    public function label(): string
    {
        return __('attendance.enums.scopes.'.$this->value);
    }

    /**
     * @return list<self>
     */
    public static function forSchedules(): array
    {
        return [self::Employee, self::EmployeeGroup, self::Department, self::Branch, self::Company];
    }

    /**
     * @return list<self>
     */
    public static function forPolicies(): array
    {
        return [self::Employee, self::EmployeeGroup, self::EmploymentType, self::Department, self::Branch, self::Company];
    }

    /**
     * @return class-string<Model>|null
     */
    public function modelClass(): ?string
    {
        return match ($this) {
            self::Employee => Employee::class,
            self::EmployeeGroup => EmployeeGroup::class,
            self::EmploymentType => EmploymentType::class,
            self::Department => Department::class,
            self::Branch => Branch::class,
            self::Company => null,
        };
    }
}
