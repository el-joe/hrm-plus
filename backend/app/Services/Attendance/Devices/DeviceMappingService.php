<?php

namespace App\Services\Attendance\Devices;

use App\Enums\RawEventStatus;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceEvent;
use App\Models\AttendanceDeviceUser;
use App\Models\AttendanceRawEvent;
use App\Models\Employee;
use App\Models\User;
use App\Services\Attendance\AttendanceRecalculation;
use App\Services\Attendance\RawEventRecorder;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Device user id -> employee mappings, and resolving unmatched device events:
 * creating a mapping attaches every waiting unmatched event of that device
 * user to the employee (RawEventRecorder::matchEmployee) and recalculates the
 * affected days. Removing a mapping never touches events already attached.
 */
class DeviceMappingService
{
    public function __construct(
        private readonly RawEventRecorder $recorder,
        private readonly AttendanceRecalculation $recalculation,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Map a device user id (on one device, or on every device when $device is null).
     *
     * @return array{mapping: AttendanceDeviceUser, matched: int}
     */
    public function map(?AttendanceDevice $device, string $deviceUserId, Employee $employee, ?User $user, ?string $nameOnDevice = null): array
    {
        $mapping = DB::transaction(fn (): AttendanceDeviceUser => AttendanceDeviceUser::query()->withoutGlobalScopes()->updateOrCreate(
            ['device_scope' => $device?->id ?? 0, 'device_user_id' => $deviceUserId],
            ['attendance_device_id' => $device?->id, 'employee_id' => $employee->id, 'name_on_device' => $nameOnDevice, 'created_by' => $user?->id],
        ));

        return ['mapping' => $mapping, 'matched' => $this->matchWaiting($device, $deviceUserId, $employee)];
    }

    /**
     * @param  array<string, int>  $rows  device user id => employee id
     * @return array{mapped: int, matched: int}
     */
    public function bulk(?AttendanceDevice $device, array $rows, ?User $user): array
    {
        $employees = Employee::query()->whereIn('id', array_values($rows))->get()->keyBy('id');
        $totals = ['mapped' => 0, 'matched' => 0];

        foreach ($rows as $deviceUserId => $employeeId) {
            if (($employee = $employees->get($employeeId)) === null) {
                continue;
            }

            $result = $this->map($device, (string) $deviceUserId, $employee, $user);
            $totals['mapped']++;
            $totals['matched'] += $result['matched'];
        }

        $this->audit->log('attendance_device.mappings_saved', $device, meta: $totals, actor: $user);

        return $totals;
    }

    public function remove(AttendanceDeviceUser $mapping): void
    {
        $mapping->delete();
    }

    /**
     * Attach one unmatched raw event to an employee; with $remember (and a device
     * event) the device user id is mapped too, which resolves its other waiting events.
     *
     * @return int events attached
     */
    public function matchEvent(AttendanceRawEvent $event, Employee $employee, bool $remember, ?User $user): int
    {
        $deviceEvent = AttendanceDeviceEvent::query()->where('attendance_raw_event_id', $event->id)->first();

        if ($remember && $deviceEvent !== null) {
            $device = AttendanceDevice::query()->withoutGlobalScopes()->find($deviceEvent->attendance_device_id);
            $matched = $this->map($device, $deviceEvent->device_user_id, $employee, $user)['matched'];
        } else {
            $this->recorder->matchEmployee($event, $employee);
            $matched = 1;
        }

        $this->audit->log('attendance.event_matched', $event, newValues: ['employee_id' => $employee->id], meta: ['remember' => $remember, 'events' => $matched], actor: $user);

        return $matched;
    }

    private function matchWaiting(?AttendanceDevice $device, string $deviceUserId, Employee $employee): int
    {
        $events = AttendanceRawEvent::query()->withoutGlobalScopes()
            ->where('status', RawEventStatus::Unmatched)
            ->whereIn('id', AttendanceDeviceEvent::query()
                ->where('device_user_id', $deviceUserId)
                ->when($device !== null, fn ($query) => $query->where('attendance_device_id', $device->id))
                ->select('attendance_raw_event_id'))
            ->get();

        $dates = [];

        foreach ($events as $event) {
            $this->recorder->matchEmployee($event, $employee, process: 'none');
            $dates = [...$dates, ...$this->recorder->affectedDates($event)];
        }

        $this->recalculation->queue($employee->id, array_values(array_unique($dates)));

        return $events->count();
    }
}
