<?php

namespace App\Models;

use App\Enums\DeviceConnectionMode;
use App\Enums\DeviceHealth;
use App\Enums\DeviceSyncStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasDataScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A physical attendance terminal (fingerprint / card / face) of a branch.
 * Talks to HRM Plus through an adapter (config/attendance_devices.php); until
 * a vendor adapter is verified on real hardware, only the SIMULATED adapter
 * exists. Pull credentials are encrypted; the push token is stored as a hash.
 *
 * Scoped by branch: a device without a branch is visible to unrestricted admins only.
 */
#[Fillable([
    'name', 'serial_number', 'branch_id', 'branch_location_id', 'adapter', 'connection_mode', 'host', 'port',
    'credentials', 'timezone', 'pull_interval_minutes', 'options', 'is_active',
])]
#[Hidden(['credentials', 'push_token_hash'])]
class AttendanceDevice extends Model
{
    use Auditable, HasActiveFlag, HasDataScope;

    /** @var list<string> */
    protected array $auditExclude = ['last_seen_at', 'last_sync_at', 'sync_cursor', 'last_sync_status', 'consecutive_failures', 'next_retry_at', 'last_error', 'updated_at'];

    /**
     * Failures in a row after which the device is shown in error.
     */
    public const ERROR_AFTER_FAILURES = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'connection_mode' => DeviceConnectionMode::class,
            'credentials' => 'encrypted',
            'options' => 'array',
            'port' => 'integer',
            'pull_interval_minutes' => 'integer',
            'consecutive_failures' => 'integer',
            'last_seen_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'last_sync_status' => DeviceSyncStatus::class,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return ['branch' => 'branch_id'];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<BranchLocation, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(BranchLocation::class, 'branch_location_id');
    }

    /**
     * Mappings of this device only (not the "every device" ones).
     *
     * @return HasMany<AttendanceDeviceUser, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(AttendanceDeviceUser::class);
    }

    /**
     * @return HasMany<AttendanceDeviceSyncLog, $this>
     */
    public function syncLogs(): HasMany
    {
        return $this->hasMany(AttendanceDeviceSyncLog::class)->latest('started_at')->latest('id');
    }

    /**
     * @return HasOne<AttendanceDeviceSyncLog, $this>
     */
    public function latestSyncLog(): HasOne
    {
        return $this->hasOne(AttendanceDeviceSyncLog::class)->latestOfMany('started_at');
    }

    /**
     * @return HasMany<AttendanceDeviceEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AttendanceDeviceEvent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isSimulated(): bool
    {
        return (bool) config('attendance_devices.adapters.'.$this->adapter.'.simulated', false);
    }

    public function adapterLabel(): string
    {
        $key = 'devices.adapters.'.$this->adapter;

        return trans()->has($key) ? __($key) : $this->adapter;
    }

    /**
     * Timezone of the wall-clock times the device reports.
     */
    public function effectiveTimezone(): string
    {
        return $this->timezone ?: ($this->branch?->timezone ?: (string) setting('localisation.timezone'));
    }

    /**
     * Health from the sync state: disabled, never synced (unknown), failing
     * (warning, error after ERROR_AFTER_FAILURES in a row) or silent for longer
     * than the "device stale after" setting (warning).
     */
    public function health(): DeviceHealth
    {
        if (! $this->is_active) {
            return DeviceHealth::Disabled;
        }

        if ($this->consecutive_failures >= self::ERROR_AFTER_FAILURES) {
            return DeviceHealth::Error;
        }

        if ($this->consecutive_failures > 0) {
            return DeviceHealth::Warning;
        }

        $lastContact = $this->connection_mode === DeviceConnectionMode::Push ? $this->last_seen_at : ($this->last_sync_at ?? $this->last_seen_at);

        if ($lastContact === null) {
            return DeviceHealth::Unknown;
        }

        $staleHours = (int) setting('attendance.device_stale_after_hours');

        return $this->connection_mode !== DeviceConnectionMode::File && $lastContact->lt(now()->subHours($staleHours))
            ? DeviceHealth::Warning
            : DeviceHealth::Ok;
    }

    /**
     * Whether the scheduler should pull this device now.
     */
    public function isDueForPull(): bool
    {
        if (! $this->is_active || $this->connection_mode !== DeviceConnectionMode::Pull) {
            return false;
        }

        if ($this->next_retry_at !== null) {
            return $this->next_retry_at->lte(now());
        }

        return $this->last_sync_at === null || $this->last_sync_at->addMinutes(max(1, $this->pull_interval_minutes))->lte(now());
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options ?? [], $key, $default);
    }
}
