<?php

namespace App\Models;

use App\Enums\LeaveDayPart;
use Database\Factories\LeaveRequestDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One calendar day of a leave request with the amount charged on it (0 for a
 * rest day or holiday the leave type does not count). Read by the
 * attendance LeaveProvider, the balance calculator and the payroll feed.
 */
#[Fillable(['leave_request_id', 'employee_id', 'date', 'leave_year', 'day_part', 'amount', 'hours', 'is_counted', 'day_kind'])]
class LeaveRequestDay extends Model
{
    /** @use HasFactory<LeaveRequestDayFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'leave_year' => 'integer',
            'day_part' => LeaveDayPart::class,
            'amount' => 'decimal:2',
            'hours' => 'decimal:2',
            'is_counted' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class, 'leave_request_id');
    }
}
