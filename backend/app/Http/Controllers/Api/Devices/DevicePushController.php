<?php

namespace App\Http\Controllers\Api\Devices;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDevice;
use App\Services\Attendance\Devices\DeviceAdapterRegistry;
use App\Services\Attendance\Devices\DeviceSyncException;
use App\Services\Attendance\Devices\DeviceSyncService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/devices/{serial}/events   (Authorization: Bearer <device push token>)
 *
 * Attendance logs pushed by a device or a vendor bridge. The token is the
 * one generated for that device (stored hashed); the payload format is the
 * device adapter's (simulated / generic JSON: docs/ATTENDANCE_DEVICES.md).
 * Idempotent: re-sending a batch reports the logs as duplicates.
 */
class DevicePushController extends Controller
{
    public function __invoke(Request $request, string $serial, DeviceAdapterRegistry $adapters, DeviceSyncService $sync): JsonResponse
    {
        $device = AttendanceDevice::query()->withoutGlobalScopes()->where('serial_number', $serial)->first();
        $token = (string) $request->bearerToken();

        if ($device === null || $token === '' || $device->push_token_hash === null || ! hash_equals($device->push_token_hash, hash('sha256', $token))) {
            return ApiResponse::error(__('devices.push.unauthorised'), 401, 'device_unauthorised');
        }

        if (! $device->is_active) {
            return ApiResponse::error(__('devices.push.inactive'), 403, 'device_inactive');
        }

        try {
            $punches = $adapters->for($device)->parsePush($device, $request);
        } catch (DeviceSyncException $exception) {
            $sync->recordPushFailure($device, $exception->getMessage());

            return ApiResponse::error($exception->getMessage(), 422, 'invalid_payload');
        }

        $log = $sync->recordPush($device, $punches);

        return ApiResponse::success([
            'received' => $log->fetched,
            'created' => $log->created,
            'duplicates' => $log->duplicates,
            'unmatched' => $log->unmatched,
            'sync_log_id' => $log->id,
            'simulated' => $log->is_simulated,
        ]);
    }
}
