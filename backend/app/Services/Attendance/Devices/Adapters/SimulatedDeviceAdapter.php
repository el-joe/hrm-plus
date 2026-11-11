<?php

namespace App\Services\Attendance\Devices\Adapters;

use App\Enums\PunchDirection;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceUser;
use App\Models\Employee;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\Devices\Contracts\AttendanceDeviceAdapter;
use App\Services\Attendance\Devices\Data\ConnectionResult;
use App\Services\Attendance\Devices\Data\DevicePunch;
use App\Services\Attendance\Devices\Data\PullResult;
use App\Services\Attendance\Devices\DeviceSyncException;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * SIMULATED device for development and demos. It contacts no hardware.
 *
 * - pull: invents plausible, deterministic logs for the employees mapped to
 *   the device (around their scheduled shifts, some late, some absent) plus
 *   one unknown user id (option "unknown_user_id", default 9001), between the
 *   cursor and now. The same window always yields the same logs, so pulling
 *   twice produces duplicates, never extra punches.
 * - push: accepts the generic JSON format documented in
 *   docs/ATTENDANCE_DEVICES.md: {"events": [{"user_id", "time", "direction"?, "log_id"?, "verify_mode"?}]}.
 * - option "simulate_failure": every pull / test fails (to exercise retries and health).
 *
 * Every screen and log labels it "Simulated"; never present it as a real device integration.
 */
class SimulatedDeviceAdapter implements AttendanceDeviceAdapter
{
    public function __construct(private readonly ScheduleResolver $schedules) {}

    public function testConnection(AttendanceDevice $device): ConnectionResult
    {
        if ($device->option('simulate_failure')) {
            return new ConnectionResult(false, __('devices.simulated.failure'));
        }

        return new ConnectionResult(true, __('devices.simulated.test_ok'), [
            'adapter' => 'simulated',
            'mapped_users' => $this->mappings($device)->count(),
        ]);
    }

    public function pull(AttendanceDevice $device, ?string $cursor, ?CarbonImmutable $from = null, ?CarbonImmutable $to = null): PullResult
    {
        Log::info('[SIMULATED] attendance device pull', ['device' => $device->serial_number]);

        if ($device->option('simulate_failure')) {
            throw new DeviceSyncException(__('devices.simulated.failure'));
        }

        $now = CarbonImmutable::now()->utc();
        $to = ($to ?? $now)->min($now);
        $from ??= $cursor !== null ? $this->parseCursor($cursor) : $now->subDay();
        $from = $from->max($to->subDays((int) config('attendance_devices.max_pull_days')));

        $punches = [];
        $timezone = $device->effectiveTimezone();
        $day = AttendanceClock::localDate($from, $timezone);
        $lastDay = AttendanceClock::localDate($to, $timezone);

        while ($day->lte($lastDay)) {
            foreach ($this->mappings($device) as $mapping) {
                foreach ($this->dayLogs($device, $mapping, $day) as $punch) {
                    if ($punch->occurredAt->gt($from) && $punch->occurredAt->lte($to)) {
                        $punches[] = $punch;
                    }
                }
            }

            $unknown = (string) $device->option('unknown_user_id', '9001');
            $this->seed($device, $unknown, $day);

            if (! in_array($day->dayOfWeek, [5, 6], true) && mt_rand(1, 3) === 1) {
                $at = AttendanceClock::instant($day, '08:05', $timezone)->addMinutes(mt_rand(-5, 15));

                if ($at->gt($from) && $at->lte($to)) {
                    $punches[] = $this->punch($unknown, $at, PunchDirection::In);
                }
            }

            $day = $day->addDay();
        }

        usort($punches, fn (DevicePunch $a, DevicePunch $b): int => $a->occurredAt <=> $b->occurredAt);

        return new PullResult($punches, $to->toIso8601ZuluString(), __('devices.simulated.pull_note'));
    }

    public function parsePush(AttendanceDevice $device, Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'events' => ['required', 'array', 'min:1', 'max:'.config('attendance_devices.push_max_events')],
            'events.*.user_id' => ['required', 'string', 'max:64'],
            'events.*.time' => ['required', 'string', 'max:40'],
            'events.*.direction' => ['nullable', 'in:in,out,unknown'],
            'events.*.log_id' => ['nullable', 'string', 'max:100'],
            'events.*.verify_mode' => ['nullable', 'string', 'max:30'],
        ]);

        if ($validator->fails()) {
            throw new DeviceSyncException($validator->errors()->first());
        }

        $timezone = $device->effectiveTimezone();

        return array_map(function (array $event) use ($timezone): DevicePunch {
            try {
                // An ISO-8601 time with an offset is absolute; a plain "Y-m-d H:i:s" is device wall-clock time.
                $at = CarbonImmutable::parse($event['time'], $timezone)->utc();
            } catch (Throwable) {
                throw new DeviceSyncException(__('devices.push.invalid_time', ['time' => $event['time']]));
            }

            return new DevicePunch(
                (string) $event['user_id'], $at, PunchDirection::from($event['direction'] ?? 'unknown'),
                $event['log_id'] ?? null, $event['verify_mode'] ?? null, ['time' => $event['time']],
            );
        }, array_values($validator->validated()['events']));
    }

    /**
     * Logs of one mapped user on one local date (deterministic).
     *
     * @return list<DevicePunch>
     */
    private function dayLogs(AttendanceDevice $device, AttendanceDeviceUser $mapping, CarbonImmutable $day): array
    {
        $employee = $mapping->employee;

        if (! $employee instanceof Employee || $employee->isTerminated()) {
            return [];
        }

        $resolved = $this->schedules->for($employee, $day);

        if (! $resolved->isWorkingDay()) {
            return [];
        }

        $this->seed($device, $mapping->device_user_id, $day);

        if (mt_rand(1, 100) <= 4) {
            return [];
        }

        $logs = [];

        foreach ($resolved->shifts as $shift) {
            $late = mt_rand(1, 100) <= 15;
            $in = $shift->startAt->addMinutes($late ? mt_rand(16, 45) : mt_rand(-20, 8));
            $out = $shift->endAt->addMinutes(mt_rand(-5, 40));
            $logs[] = $this->punch($mapping->device_user_id, $in, PunchDirection::In);
            $logs[] = $this->punch($mapping->device_user_id, $out, PunchDirection::Out);
        }

        return $logs;
    }

    private function punch(string $userId, CarbonImmutable $at, PunchDirection $direction): DevicePunch
    {
        $at = $at->startOfMinute()->addSeconds(abs(crc32($userId.$at->getTimestamp())) % 60)->utc();

        return new DevicePunch($userId, $at, $direction, 'SIM-'.$userId.'-'.$at->getTimestamp(), 'simulated', ['simulated' => true]);
    }

    private function seed(AttendanceDevice $device, string $userId, CarbonImmutable $day): void
    {
        mt_srand(crc32($device->serial_number.'|'.$userId.'|'.$day->format('Y-m-d')));
    }

    /**
     * @return Collection<int, AttendanceDeviceUser>
     */
    private function mappings(AttendanceDevice $device): Collection
    {
        return AttendanceDeviceUser::query()->withoutGlobalScopes()->with(['employee' => fn ($query) => $query->withoutGlobalScopes()])
            ->where(fn ($query) => $query->where('attendance_device_id', $device->id)
                ->orWhere(fn ($query) => $query->whereNull('attendance_device_id')
                    ->whereHas('employee', fn ($query) => $query->withoutGlobalScopes()->where('branch_id', $device->branch_id))))
            ->orderBy('device_user_id')
            ->get();
    }

    private function parseCursor(string $cursor): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($cursor)->utc();
        } catch (Throwable) {
            return CarbonImmutable::now()->utc()->subDay();
        }
    }
}
