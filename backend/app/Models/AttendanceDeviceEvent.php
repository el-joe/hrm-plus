<?php

namespace App\Models;

use Database\Factories\AttendanceDeviceEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The device side of a raw attendance event: which device, which device
 * user id, which sync run. Keeps the raw event table source-neutral and lets
 * unmatched events be mapped later by device user.
 */
class AttendanceDeviceEvent extends Model
{
    /** @use HasFactory<AttendanceDeviceEventFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * @return BelongsTo<AttendanceRawEvent, $this>
     */
    public function rawEvent(): BelongsTo
    {
        return $this->belongsTo(AttendanceRawEvent::class, 'attendance_raw_event_id');
    }

    /**
     * @return BelongsTo<AttendanceDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id')->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<AttendanceDeviceSyncLog, $this>
     */
    public function syncLog(): BelongsTo
    {
        return $this->belongsTo(AttendanceDeviceSyncLog::class, 'attendance_device_sync_log_id')->withoutGlobalScopes();
    }
}
