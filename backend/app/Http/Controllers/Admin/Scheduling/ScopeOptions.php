<?php

namespace App\Http\Controllers\Admin\Scheduling;

use App\Enums\AttendanceScope;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use Illuminate\Database\Eloquent\Model;

/**
 * Records offered by the "applies to" selects of assignment forms, inside
 * the admin's data scope (active ones, plus the current value).
 */
class ScopeOptions
{
    /**
     * @param  list<AttendanceScope>  $types
     * @return array<string, list<array{id: int, label: string}>>
     */
    public static function for(array $types, ?AttendanceScope $currentType = null, ?int $currentId = null): array
    {
        $options = [];

        foreach ($types as $type) {
            $keep = $type === $currentType ? $currentId : null;

            $options[$type->value] = match ($type) {
                AttendanceScope::Employee => Employee::query()->activeOrKeep($keep)->orderBy('employee_number')->get()
                    ->map(fn (Employee $employee): array => ['id' => $employee->id, 'label' => $employee->label()])->all(),
                AttendanceScope::EmployeeGroup => self::coded(EmployeeGroup::query()->activeOrKeep($keep)->orderBy('code')->get()),
                AttendanceScope::EmploymentType => self::coded(EmploymentType::query()->activeOrKeep($keep)->orderBy('code')->get()),
                AttendanceScope::Department => self::coded(Department::query()->activeOrKeep($keep)->orderBy('code')->get()),
                AttendanceScope::Branch => self::coded(Branch::query()->activeOrKeep($keep)->orderBy('code')->get()),
                AttendanceScope::Company => [],
            };
        }

        return $options;
    }

    /**
     * @param  iterable<int, Model>  $records
     * @return list<array{id: int, label: string}>
     */
    private static function coded(iterable $records): array
    {
        $options = [];

        foreach ($records as $record) {
            $options[] = ['id' => (int) $record->getKey(), 'label' => $record->code.' · '.$record->name];
        }

        return $options;
    }
}
