<?php

namespace App\Http\Controllers\Admin\Attendance;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;
use App\Models\Employee;
use App\Rules\ExistsInScope;
use App\Services\Attendance\Devices\DeviceMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Bulk device-user mapping of a device (this device only, or every device):
 * saving resolves the waiting unmatched events of each mapped id.
 */
class DeviceMappingController extends Controller
{
    public function store(Request $request, AttendanceDevice $attendanceDevice, DeviceMappingService $mappings): RedirectResponse
    {
        Gate::authorize('manage', $attendanceDevice);

        $data = $request->validate([
            'scope' => ['required', 'in:device,all'],
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*.device_user_id' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'rows.*.employee_id' => ['nullable', 'integer', new ExistsInScope(Employee::class)],
        ], [], ['rows.*.device_user_id' => __('devices.fields.device_user_id'), 'rows.*.employee_id' => __('devices.fields.employee')]);

        $rows = [];

        foreach ($data['rows'] as $row) {
            if (filled($row['device_user_id'] ?? null) && filled($row['employee_id'] ?? null)) {
                $rows[(string) $row['device_user_id']] = (int) $row['employee_id'];
            }
        }

        if ($rows === []) {
            return back()->withErrors(['rows' => __('devices.mappings.nothing_selected')]);
        }

        $result = $mappings->bulk($data['scope'] === 'all' ? null : $attendanceDevice, $rows, $request->user());

        return back()->with('status', __('devices.flash.mapped', $result));
    }

    public function destroy(AttendanceDevice $attendanceDevice, AttendanceDeviceUser $mapping, DeviceMappingService $mappings): RedirectResponse
    {
        Gate::authorize('manage', $attendanceDevice);
        abort_unless($mapping->attendance_device_id === null || $mapping->attendance_device_id === $attendanceDevice->id, 404);

        $mappings->remove($mapping);

        return back()->with('status', __('devices.flash.mapping_removed'));
    }
}
