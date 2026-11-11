<?php

namespace App\Services\Attendance\Devices;

use App\Enums\DeviceSyncType;
use App\Enums\PunchDirection;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceSyncLog;
use App\Models\AttendanceRawEvent;
use App\Models\User;
use App\Services\Attendance\Devices\Data\DevicePunch;
use App\Services\Employees\EmployeeFileStore;
use App\Services\Employees\Spreadsheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

/**
 * CSV / Excel fallback for devices without a working integration: the
 * attendance log exported from the device (or its vendor software) is
 * uploaded, checked in a dry run (nothing written), then imported as device
 * events of that device — same idempotency keys as pull / push, so a log
 * that already arrived another way is reported as a duplicate.
 *
 * Columns: device_user_id, time ("Y-m-d H:i[:s]" device time, or ISO-8601
 * with offset), direction (in / out, optional), log_id (optional).
 */
class DeviceLogImporter
{
    /** @var list<string> */
    public const COLUMNS = ['device_user_id', 'time', 'direction', 'log_id'];

    public function __construct(
        private readonly Spreadsheet $spreadsheet,
        private readonly DeviceEventIngestor $ingestor,
        private readonly DeviceSyncService $sync,
    ) {}

    public function stash(UploadedFile $file, User $user): string
    {
        $token = Str::uuid()->toString();
        $extension = in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt'], true) ? 'csv' : 'xlsx';
        app(EmployeeFileStore::class)->disk()->putFileAs($this->folder($user), $file, $token.'.'.$extension);

        return $token;
    }

    /**
     * @return array{path: string, extension: string}|null
     */
    public function stashed(string $token, User $user): ?array
    {
        if (! Str::isUuid($token)) {
            return null;
        }

        $disk = app(EmployeeFileStore::class)->disk();

        foreach (['xlsx', 'csv'] as $extension) {
            $relative = $this->folder($user).'/'.$token.'.'.$extension;

            if ($disk->exists($relative) && $disk->lastModified($relative) >= now()->subMinutes(config('employees.import_ttl_minutes'))->getTimestamp()) {
                return ['path' => $disk->path($relative), 'extension' => $extension];
            }
        }

        return null;
    }

    public function discard(string $token, User $user): void
    {
        foreach (['xlsx', 'csv'] as $extension) {
            app(EmployeeFileStore::class)->disk()->delete($this->folder($user).'/'.$token.'.'.$extension);
        }
    }

    /**
     * Dry run: parse and classify every row without writing anything.
     *
     * @return array{total: int, new: int, duplicates: int, unmatched: int, errors: list<array{line: int, column: string, message: string}>, punches: array<int, DevicePunch>, preview: list<array<string, mixed>>}
     */
    public function analyse(AttendanceDevice $device, string $path, string $extension): array
    {
        $report = ['total' => 0, 'new' => 0, 'duplicates' => 0, 'unmatched' => 0, 'errors' => [], 'punches' => [], 'preview' => []];
        $rows = $this->spreadsheet->read($path, $extension, (int) config('attendance_devices.import_max_rows'));

        if ($rows === []) {
            $report['errors'][] = ['line' => 1, 'column' => '', 'message' => __('devices.import.empty')];

            return $report;
        }

        $missing = array_diff(['device_user_id', 'time'], array_keys(reset($rows)));

        if ($missing !== []) {
            $report['errors'][] = ['line' => 1, 'column' => implode(', ', $missing), 'message' => __('devices.import.missing_columns')];

            return $report;
        }

        $mappings = $this->ingestor->mappings($device);
        $timezone = $device->effectiveTimezone();
        $keys = [];
        $seen = [];

        foreach ($rows as $line => $row) {
            if (isset($row['__too_many__'])) {
                $report['errors'][] = ['line' => $line, 'column' => '', 'message' => __('devices.import.too_many_rows', ['max' => config('attendance_devices.import_max_rows')])];

                break;
            }

            $report['total']++;
            $userId = trim($row['device_user_id'] ?? '');
            $direction = strtolower(trim($row['direction'] ?? ''));

            if ($userId === '' || mb_strlen($userId) > 64) {
                $report['errors'][] = ['line' => $line, 'column' => 'device_user_id', 'message' => __('devices.import.invalid_user')];

                continue;
            }

            if ($direction !== '' && ! in_array($direction, ['in', 'out', 'unknown'], true)) {
                $report['errors'][] = ['line' => $line, 'column' => 'direction', 'message' => __('devices.import.invalid_direction')];

                continue;
            }

            try {
                $at = CarbonImmutable::parse(trim($row['time']), $timezone)->utc();
            } catch (Throwable) {
                $report['errors'][] = ['line' => $line, 'column' => 'time', 'message' => __('devices.import.invalid_time')];

                continue;
            }

            if ($at->isFuture()) {
                $report['errors'][] = ['line' => $line, 'column' => 'time', 'message' => __('devices.import.future_time')];

                continue;
            }

            $key = DeviceEventIngestor::idempotencyKey($device, $userId, $at->getTimestamp());
            $punch = new DevicePunch($userId, $at, PunchDirection::from($direction ?: 'unknown'), trim($row['log_id'] ?? '') ?: null, 'file');

            if (isset($seen[$key])) {
                $report['duplicates']++;
                $state = 'duplicate_in_file';
            } else {
                $seen[$key] = $line;
                $keys[$key] = $line;
                $report['punches'][$line] = $punch;
                $state = isset($mappings[$userId]) ? 'new' : 'unmatched';
            }

            if (count($report['preview']) < 200) {
                $report['preview'][] = ['line' => $line, 'user' => $userId, 'time' => $at->setTimezone($timezone)->format('Y-m-d H:i:s'),
                    'direction' => $punch->direction, 'state' => $state, 'key' => $key];
            }
        }

        $existing = AttendanceRawEvent::query()->withoutGlobalScopes()->whereIn('idempotency_key', array_keys($keys))->pluck('idempotency_key')->flip();

        foreach ($keys as $key => $line) {
            if ($existing->has($key)) {
                $report['duplicates']++;
                unset($report['punches'][$line]);

                continue;
            }

            isset($mappings[$report['punches'][$line]->deviceUserId]) ? $report['new']++ : $report['unmatched']++;
        }

        foreach ($report['preview'] as $index => $row) {
            if ($row['state'] !== 'duplicate_in_file' && $existing->has($row['key'])) {
                $report['preview'][$index]['state'] = 'duplicate';
            }
        }

        return $report;
    }

    /**
     * Import the rows of a file that passed the dry run without errors.
     *
     * @return array{report: array<string, mixed>, log: ?AttendanceDeviceSyncLog}
     */
    public function import(AttendanceDevice $device, string $path, string $extension, string $fileName, User $user): array
    {
        $report = $this->analyse($device, $path, $extension);

        if ($report['errors'] !== [] || $report['punches'] === []) {
            return ['report' => $report, 'log' => null];
        }

        $log = $this->sync->start($device, DeviceSyncType::File, $user, fileName: Str::limit($fileName, 250, ''));
        $counts = $this->ingestor->ingest($device, array_values($report['punches']), $log);
        $counts['duplicates'] += $report['duplicates'];
        $counts['fetched'] = $report['total'];

        return ['report' => $report, 'log' => $this->sync->succeed($device, $log, $counts, moveCursor: null, markSynced: false)];
    }

    private function folder(User $user): string
    {
        return 'device-imports/'.$user->getKey();
    }
}
