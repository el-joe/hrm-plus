<?php

namespace App\Services\Attendance\Devices;

use App\Enums\RawEventSource;
use App\Enums\RawEventStatus;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceEvent;
use App\Models\AttendanceDeviceSyncLog;
use App\Models\AttendanceDeviceUser;
use App\Services\Attendance\AttendanceRecalculation;
use App\Services\Attendance\Data\RawEventData;
use App\Services\Attendance\Devices\Data\DevicePunch;
use App\Services\Attendance\RawEventRecorder;

/**
 * Turns device logs into raw attendance events, whatever the transport
 * (pull, push, historical import, CSV / Excel file):
 *
 * - idempotency key "device:{serial}:{device user id}:{unix time}": the same
 *   log arriving twice (re-pull, push + file) is a duplicate, never a second
 *   punch (unique index on attendance_raw_events);
 * - the device user id is mapped to an employee (device-specific mapping
 *   first, then the "every device" one); unknown ids are stored as unmatched
 *   events, to be mapped later (DeviceMappingService);
 * - processing is queued once per employee after the batch.
 */
class DeviceEventIngestor
{
    public function __construct(
        private readonly RawEventRecorder $recorder,
        private readonly AttendanceRecalculation $recalculation,
    ) {}

    public static function idempotencyKey(AttendanceDevice $device, string $deviceUserId, int $timestamp): string
    {
        return 'device:'.$device->serial_number.':'.$deviceUserId.':'.$timestamp;
    }

    /**
     * @param  iterable<DevicePunch>  $punches
     * @param  'queue'|'now'  $process
     * @return array{fetched: int, created: int, duplicates: int, unmatched: int}
     */
    public function ingest(AttendanceDevice $device, iterable $punches, ?AttendanceDeviceSyncLog $log = null, string $process = 'queue'): array
    {
        $mappings = $this->mappings($device);
        $counts = ['fetched' => 0, 'created' => 0, 'duplicates' => 0, 'unmatched' => 0];
        $affected = [];
        $timezone = $device->effectiveTimezone();

        foreach ($punches as $punch) {
            $counts['fetched']++;
            $employeeId = $mappings[$punch->deviceUserId] ?? null;

            $event = $this->recorder->record(RawEventData::punch(
                RawEventSource::Device, $employeeId, $punch->occurredAt, $punch->direction, $timezone,
                self::idempotencyKey($device, $punch->deviceUserId, $punch->occurredAt->getTimestamp()),
                sourceReference: mb_substr($device->serial_number.'/'.$punch->deviceUserId, 0, 191),
                payload: array_filter([
                    'device_serial' => $device->serial_number,
                    'device_user_id' => $punch->deviceUserId,
                    'device_log_id' => $punch->deviceLogId,
                    'verify_mode' => $punch->verifyMode,
                    'simulated' => $device->isSimulated() ?: null,
                ], fn ($value) => $value !== null),
                origin: $device,
            ), process: 'none');

            // Created now, or left behind by an interrupted earlier run: link it to the device either way.
            AttendanceDeviceEvent::query()->firstOrCreate(['attendance_raw_event_id' => $event->id], [
                'attendance_device_id' => $device->id,
                'attendance_device_sync_log_id' => $log?->id,
                'device_user_id' => $punch->deviceUserId,
                'device_log_id' => $punch->deviceLogId,
                'verify_mode' => $punch->verifyMode,
            ]);

            if (! $event->wasRecentlyCreated) {
                $counts['duplicates']++;

                continue;
            }

            $counts['created']++;

            if ($event->status === RawEventStatus::Unmatched) {
                $counts['unmatched']++;

                continue;
            }

            $affected[$event->employee_id] = [...($affected[$event->employee_id] ?? []), ...$this->recorder->affectedDates($event)];
        }

        foreach ($affected as $employeeId => $dates) {
            $dates = array_values(array_unique($dates));
            $process === 'now' ? $this->recalculation->now($employeeId, $dates) : $this->recalculation->queue($employeeId, $dates);
        }

        return $counts;
    }

    /**
     * Device user id => employee id (device-specific mappings override "every device" ones).
     *
     * @return array<string, int>
     */
    public function mappings(AttendanceDevice $device): array
    {
        return AttendanceDeviceUser::query()->withoutGlobalScopes()
            ->whereIn('device_scope', [0, $device->id])
            ->orderBy('device_scope')
            ->get(['device_user_id', 'employee_id'])
            ->mapWithKeys(fn (AttendanceDeviceUser $row): array => [(string) $row->device_user_id => (int) $row->employee_id])
            ->all();
    }
}
