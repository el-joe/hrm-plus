<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\AttendancePolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An attendance policy. Its rules live in effective-dated versions
 * (attendance_policy_versions); it is assigned through
 * attendance_policy_assignments.
 */
#[Fillable(['code', 'name_ar', 'name_en', 'description', 'is_active'])]
class AttendancePolicy extends Model implements GuardsDeletion
{
    /** @use HasFactory<AttendancePolicyFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    /**
     * @return HasMany<AttendancePolicyVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(AttendancePolicyVersion::class)->orderByDesc('effective_from');
    }

    /**
     * @return HasMany<AttendancePolicyAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AttendancePolicyAssignment::class)->orderByDesc('effective_from');
    }

    /**
     * The version in force on a date (Y-m-d), if any.
     */
    public function versionOn(string $date): ?AttendancePolicyVersion
    {
        return $this->versions()->where('effective_from', '<=', $date)->first();
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'policy_assignments' => $this->assignments()->count(),
            'attendance_days' => AttendanceDay::query()->withoutGlobalScopes()
                ->whereIn('attendance_policy_version_id', $this->versions()->select('id'))->count(),
        ];
    }
}
