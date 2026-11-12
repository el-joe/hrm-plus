<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use Database\Factories\AttendanceDeviceUserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps the user id enrolled on a device to an employee. attendance_device_id
 * null (device_scope 0) means the id is the same on every device. Written by
 * App\Services\Attendance\Devices\DeviceMappingService only (it re-processes
 * waiting unmatched events).
 */
#[Fillable(['attendance_device_id', 'device_scope', 'device_user_id', 'employee_id', 'name_on_device', 'created_by'])]
class AttendanceDeviceUser extends Model
{
    /** @use HasFactory<AttendanceDeviceUserFactory> */
    use Auditable, HasDataScope, HasFactory;

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'employee.branch_id',
            'department' => 'employee.department_id',
            'employee_group' => 'employee.groups.id',
        ];
    }

    /**
     * @return BelongsTo<AttendanceDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function appliesToAllDevices(): bool
    {
        return $this->attendance_device_id === null;
    }
}
