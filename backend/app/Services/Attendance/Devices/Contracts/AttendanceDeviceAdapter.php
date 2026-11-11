<?php

namespace App\Services\Attendance\Devices\Contracts;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Data\ConnectionResult;
use App\Services\Attendance\Devices\Data\DevicePunch;
use App\Services\Attendance\Devices\Data\PullResult;
use App\Services\Attendance\Devices\DeviceSyncException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The one integration point for attendance terminals. A vendor adapter
 * translates its protocol (vendor SDK, ADMS-style push, HTTP API, ...) into
 * DevicePunch values; everything after that (idempotency, mapping to
 * employees, raw events, processing, logs, health) is shared.
 *
 * Register adapters in config/attendance_devices.php. Never register an
 * adapter for a brand that was not tested on the real device and firmware.
 */
interface AttendanceDeviceAdapter
{
    /**
     * Check that the device can be reached with its configuration.
     */
    public function testConnection(AttendanceDevice $device): ConnectionResult;

    /**
     * Logs recorded after $cursor (the value returned by the previous pull;
     * null on the first pull), optionally limited to a time range (historical
     * import). Must be safe to call repeatedly: returning a log twice is fine,
     * the idempotency key drops it.
     *
     * @throws DeviceSyncException when the device cannot be read
     */
    public function pull(AttendanceDevice $device, ?string $cursor, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): PullResult;

    /**
     * Logs contained in a push request the device (or a vendor bridge) sent.
     * The request was already authenticated for this device.
     *
     * @return list<DevicePunch>
     *
     * @throws DeviceSyncException when the payload cannot be parsed
     */
    public function parsePush(AttendanceDevice $device, Request $request): array;
}
