<?php

namespace App\Services\Attendance\Devices;

use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\Contracts\AttendanceDeviceAdapter;
use InvalidArgumentException;

/**
 * Resolves the adapter of a device from config/attendance_devices.php.
 */
class DeviceAdapterRegistry
{
    public function for(AttendanceDevice $device): AttendanceDeviceAdapter
    {
        return $this->make($device->adapter);
    }

    public function make(string $key): AttendanceDeviceAdapter
    {
        $class = config('attendance_devices.adapters.'.$key.'.class');

        if (! is_string($class) || ! is_a($class, AttendanceDeviceAdapter::class, true)) {
            throw new InvalidArgumentException("Unknown attendance device adapter [{$key}].");
        }

        return app($class);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(config('attendance_devices.adapters', []));
    }

    /**
     * @return list<string> connection modes the adapter supports
     */
    public function modes(string $key): array
    {
        return config('attendance_devices.adapters.'.$key.'.modes', []);
    }

    public function isSimulated(string $key): bool
    {
        return (bool) config('attendance_devices.adapters.'.$key.'.simulated', false);
    }
}
