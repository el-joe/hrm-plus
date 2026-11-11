<?php

namespace App\Services\Attendance\Devices;

use App\Enums\DeviceSyncStatus;
use App\Enums\DeviceSyncType;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceSyncLog;
use App\Models\User;
use App\Services\Attendance\Devices\Data\DevicePunch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs device synchronisations and keeps the device's sync state:
 *
 * - pull (scheduled every few minutes by attendance:sync-devices, or "Sync
 *   now"): from the stored cursor; the cursor only moves on success;
 * - historical import: a date range, the cursor is left alone;
 * - push batches and file imports are logged here too;
 * - a failure is logged with its message, counts as a consecutive failure
 *   and schedules the next attempt with exponential backoff
 *   (config attendance_devices.retry_*); three in a row put the device in
 *   error. Success resets the counter.
 */
class DeviceSyncService
{
    public function __construct(
        private readonly DeviceAdapterRegistry $adapters,
        private readonly DeviceEventIngestor $ingestor,
    ) {}

    public function pull(AttendanceDevice $device, ?User $user = null): AttendanceDeviceSyncLog
    {
        return $this->run($device, DeviceSyncType::Pull, $user, null, null);
    }

    public function historical(AttendanceDevice $device, CarbonImmutable $from, CarbonImmutable $to, ?User $user = null): AttendanceDeviceSyncLog
    {
        return $this->run($device, DeviceSyncType::Historical, $user, $from, $to);
    }

    public function test(AttendanceDevice $device, ?User $user = null): AttendanceDeviceSyncLog
    {
        $log = $this->start($device, DeviceSyncType::Test, $user);

        try {
            $result = $this->adapters->for($device)->testConnection($device);
        } catch (Throwable $exception) {
            return $this->fail($device, $log, $exception, countsAsFailure: false);
        }

        $log->forceFill([
            'status' => $result->ok ? DeviceSyncStatus::Success : DeviceSyncStatus::Failed,
            'finished_at' => now(),
            'message' => Str::limit($result->message.($result->details ? ' '.json_encode($result->details) : ''), 1000, ''),
        ])->save();

        if ($result->ok) {
            $device->forceFill(['last_seen_at' => now()])->save();
        }

        return $log;
    }

    /**
     * Log a push request that was received and parsed (or failed to parse).
     *
     * @param  list<DevicePunch>  $punches
     */
    public function recordPush(AttendanceDevice $device, array $punches): AttendanceDeviceSyncLog
    {
        $log = $this->start($device, DeviceSyncType::Push, null);
        $counts = $this->ingestor->ingest($device, $punches, $log);

        return $this->succeed($device, $log, $counts, moveCursor: null, markSynced: true);
    }

    public function recordPushFailure(AttendanceDevice $device, string $message): AttendanceDeviceSyncLog
    {
        $log = $this->start($device, DeviceSyncType::Push, null);
        $log->forceFill(['status' => DeviceSyncStatus::Failed, 'finished_at' => now(), 'errors' => 1, 'message' => Str::limit($message, 1000, '')])->save();
        $device->forceFill(['last_seen_at' => now()])->save();

        return $log;
    }

    public function start(AttendanceDevice $device, DeviceSyncType $type, ?User $user, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null, ?string $fileName = null): AttendanceDeviceSyncLog
    {
        return AttendanceDeviceSyncLog::query()->create([
            'attendance_device_id' => $device->id,
            'type' => $type,
            'status' => DeviceSyncStatus::Running,
            'is_simulated' => $device->isSimulated(),
            'started_at' => now(),
            'range_from' => $from,
            'range_to' => $to,
            'cursor_before' => $device->sync_cursor,
            'attempt' => $type === DeviceSyncType::Pull ? $device->consecutive_failures + 1 : 1,
            'triggered_by' => $user?->id,
            'file_name' => $fileName,
        ]);
    }

    /**
     * @param  array{fetched: int, created: int, duplicates: int, unmatched: int, errors?: int}  $counts
     */
    public function succeed(AttendanceDevice $device, AttendanceDeviceSyncLog $log, array $counts, ?string $moveCursor, bool $markSynced, ?string $message = null): AttendanceDeviceSyncLog
    {
        $errors = (int) ($counts['errors'] ?? 0);
        $log->forceFill([
            'status' => $errors > 0 ? DeviceSyncStatus::Partial : DeviceSyncStatus::Success,
            'finished_at' => now(),
            'fetched' => $counts['fetched'],
            'created' => $counts['created'],
            'duplicates' => $counts['duplicates'],
            'unmatched' => $counts['unmatched'],
            'errors' => $errors,
            'cursor_after' => $moveCursor ?? $device->sync_cursor,
            'message' => $message !== null ? Str::limit($message, 1000, '') : null,
        ])->save();

        $state = ['last_seen_at' => now()];

        if ($markSynced) {
            $state += [
                'last_sync_at' => now(), 'last_sync_status' => $log->status, 'consecutive_failures' => 0,
                'next_retry_at' => null, 'last_error' => null,
            ];
        }

        if ($moveCursor !== null) {
            $state['sync_cursor'] = $moveCursor;
        }

        $device->forceFill($state)->save();

        return $log;
    }

    private function run(AttendanceDevice $device, DeviceSyncType $type, ?User $user, ?CarbonImmutable $from, ?CarbonImmutable $to): AttendanceDeviceSyncLog
    {
        $log = $this->start($device, $type, $user, $from, $to);

        try {
            $result = $this->adapters->for($device)->pull($device, $type === DeviceSyncType::Pull ? $device->sync_cursor : null, $from, $to);
            $counts = $this->ingestor->ingest($device, $result->punches, $log);
        } catch (Throwable $exception) {
            return $this->fail($device, $log, $exception);
        }

        return $this->succeed($device, $log, $counts, $type === DeviceSyncType::Pull ? $result->cursor : null, markSynced: true, message: $result->message);
    }

    private function fail(AttendanceDevice $device, AttendanceDeviceSyncLog $log, Throwable $exception, bool $countsAsFailure = true): AttendanceDeviceSyncLog
    {
        $message = $exception instanceof DeviceSyncException ? $exception->getMessage() : __('devices.sync.unexpected_error', ['type' => class_basename($exception)]);

        if (! $exception instanceof DeviceSyncException) {
            report($exception);
        }

        Log::warning('Attendance device sync failed', ['device' => $device->serial_number, 'type' => $log->type->value, 'simulated' => $device->isSimulated()]);

        $log->forceFill(['status' => DeviceSyncStatus::Failed, 'finished_at' => now(), 'errors' => 1, 'message' => Str::limit($message, 1000, '')])->save();

        if ($countsAsFailure) {
            $failures = $device->consecutive_failures + 1;
            $minutes = min((int) config('attendance_devices.retry_base_minutes') * 2 ** ($failures - 1), (int) config('attendance_devices.retry_max_minutes'));
            $device->forceFill([
                'consecutive_failures' => $failures,
                'next_retry_at' => now()->addMinutes($minutes),
                'last_sync_status' => DeviceSyncStatus::Failed,
                'last_error' => Str::limit($message, 1000, ''),
            ])->save();
        }

        return $log;
    }
}
