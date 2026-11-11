<?php

namespace App\Actions\Attendance;

use App\Enums\DeviceConnectionMode;
use App\Models\AttendanceDevice;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Create / update attendance devices and manage their push token. The push
 * token is generated here, shown to the admin once and stored only as a
 * SHA-256 hash; rotating it invalidates the previous one immediately.
 */
class ManageAttendanceDevice
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{device: AttendanceDevice, token: ?string}
     */
    public function create(array $data, User $user): array
    {
        return DB::transaction(function () use ($data, $user): array {
            $device = new AttendanceDevice($this->attributes($data));
            $device->created_by = $user->id;
            $device->save();

            $token = $device->connection_mode === DeviceConnectionMode::Push ? $this->rotateToken($device, $user, audit: false) : null;

            return ['device' => $device, 'token' => $token];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AttendanceDevice $device, array $data): AttendanceDevice
    {
        $attributes = $this->attributes($data);

        // An empty credentials field keeps the stored secret.
        if (! filled($data['credentials'] ?? null)) {
            unset($attributes['credentials']);
        }

        $device->fill($attributes)->save();

        return $device;
    }

    /**
     * New push token (returned once, in plain text).
     */
    public function rotateToken(AttendanceDevice $device, User $user, bool $audit = true): string
    {
        $token = 'hrmd_'.Str::random(48);
        $device->forceFill(['push_token_hash' => hash('sha256', $token), 'push_token_hint' => substr($token, -4)])->save();

        if ($audit) {
            $this->audit->log('attendance_device.token_rotated', $device, actor: $user);
        }

        return $token;
    }

    public function setActive(AttendanceDevice $device, bool $active): void
    {
        $device->forceFill(['is_active' => $active, 'consecutive_failures' => 0, 'next_retry_at' => null])->save();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $options = array_filter([
            'simulate_failure' => (bool) ($data['simulate_failure'] ?? false) ?: null,
            'unknown_user_id' => $data['unknown_user_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return [
            'name' => $data['name'],
            'serial_number' => $data['serial_number'],
            'branch_id' => $data['branch_id'] ?? null,
            'branch_location_id' => $data['branch_location_id'] ?? null,
            'adapter' => $data['adapter'],
            'connection_mode' => $data['connection_mode'],
            'host' => $data['host'] ?? null,
            'port' => $data['port'] ?? null,
            'credentials' => $data['credentials'] ?? null,
            'timezone' => $data['timezone'] ?? null,
            'pull_interval_minutes' => (int) ($data['pull_interval_minutes'] ?? 15),
            'options' => $options ?: null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
