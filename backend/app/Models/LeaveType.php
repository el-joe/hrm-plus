<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\LeaveUnit;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasActiveFlag;
use App\Models\Concerns\HasLocalizedName;
use App\Models\Contracts\GuardsDeletion;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A configurable kind of leave (annual, sick, unpaid, ...): request rules,
 * eligibility and how its days are counted. Entitlements live in the
 * effective-dated LeavePolicy rows of the type.
 */
#[Fillable([
    'code', 'name_ar', 'name_en', 'description', 'is_paid', 'affects_payroll', 'tracks_balance', 'unit',
    'requires_attachment', 'attachment_after_days', 'gender', 'min_service_months', 'max_times_per_service',
    'max_days_per_request', 'notice_days', 'allows_past_dates', 'counts_rest_days', 'counts_holidays',
    'approval_workflow', 'attendance_status_id', 'color', 'sort_order', 'is_active',
])]
class LeaveType extends Model implements GuardsDeletion
{
    /** @use HasFactory<LeaveTypeFactory> */
    use Auditable, HasActiveFlag, HasFactory, HasLocalizedName;

    public const COLORS = ['chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'chart-6'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'affects_payroll' => 'boolean',
            'tracks_balance' => 'boolean',
            'unit' => LeaveUnit::class,
            'requires_attachment' => 'boolean',
            'attachment_after_days' => 'decimal:2',
            'gender' => Gender::class,
            'min_service_months' => 'integer',
            'max_times_per_service' => 'integer',
            'max_days_per_request' => 'decimal:2',
            'notice_days' => 'integer',
            'allows_past_dates' => 'boolean',
            'counts_rest_days' => 'boolean',
            'counts_holidays' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AttendanceStatus, $this>
     */
    public function attendanceStatus(): BelongsTo
    {
        return $this->belongsTo(AttendanceStatus::class);
    }

    /**
     * @return HasMany<LeavePolicy, $this>
     */
    public function policies(): HasMany
    {
        return $this->hasMany(LeavePolicy::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('code');
    }

    /**
     * Whether a request of this many days needs at least one attachment.
     */
    public function needsAttachment(string $days): bool
    {
        if (! $this->requires_attachment) {
            return false;
        }

        return $this->attachment_after_days === null || bccomp($days, (string) $this->attachment_after_days, 2) > 0;
    }

    /**
     * @return array<string, int>
     */
    public function usageCounts(): array
    {
        return [
            'leave_policies' => $this->policies()->count(),
            'leave_requests' => $this->requests()->withoutGlobalScopes()->count(),
            'leave_balance_transactions' => LeaveBalanceTransaction::query()->withoutGlobalScopes()->where('leave_type_id', $this->id)->count(),
        ];
    }
}
