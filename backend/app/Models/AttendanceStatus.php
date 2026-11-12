<?php

namespace App\Models;

use App\Enums\AttendanceStatusCategory;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\AttendanceStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A configurable day status for manual attendance (absent, on leave, official
 * mission, remote work, day off, sick leave, ...). The category tells the
 * engine what the status means.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'category', 'counts_as_worked', 'is_paid', 'sort_order', 'is_active'])]
class AttendanceStatus extends Model implements GuardsDeletion
{
    /** @use HasFactory<AttendanceStatusFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    public const ABSENT = 'absent';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => AttendanceStatusCategory::class,
            'counts_as_worked' => 'boolean',
            'is_paid' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'raw_events' => AttendanceRawEvent::query()->withoutGlobalScopes()->where('attendance_status_id', $this->getKey())->count(),
            'manual_entries' => AttendanceManualEntry::query()->where('attendance_status_id', $this->getKey())->count(),
            'corrections' => AttendanceCorrection::query()->withoutGlobalScopes()->where('attendance_status_id', $this->getKey())->count(),
        ];
    }
}
