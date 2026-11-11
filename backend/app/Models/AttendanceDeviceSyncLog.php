<?php

namespace App\Models;

use App\Enums\DeviceSyncStatus;
use App\Enums\DeviceSyncType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One synchronisation run of a device (pull, push batch, historical import,
 * file import or connection test) with its counters. Written by the device
 * services only. Visible within the device's branch scope.
 */
class AttendanceDeviceSyncLog extends Model
{
    /** @var list<string> */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::addGlobalScope('device_scope', function (Builder $query): void {
            $query->whereHas('device');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeviceSyncType::class,
            'status' => DeviceSyncStatus::class,
            'is_simulated' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'range_from' => 'datetime',
            'range_to' => 'datetime',
            'fetched' => 'integer',
            'created' => 'integer',
            'duplicates' => 'integer',
            'unmatched' => 'integer',
            'errors' => 'integer',
            'attempt' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AttendanceDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function durationSeconds(): ?int
    {
        return $this->finished_at ? (int) $this->started_at->diffInSeconds($this->finished_at) : null;
    }
}
