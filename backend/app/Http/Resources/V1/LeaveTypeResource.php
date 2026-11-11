<?php

namespace App\Http\Resources\V1;

use App\Models\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A leave type the employee may request, with the rules the app needs to build the form.
 *
 * @mixin LeaveType
 */
class LeaveTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'is_paid' => $this->is_paid,
            'tracks_balance' => $this->tracks_balance,
            'unit' => $this->unit->value,
            'allows_half_day' => $this->unit->value !== 'day' && (bool) setting('leave.allow_half_day'),
            'allows_hours' => $this->unit->value === 'hour',
            'requires_attachment' => $this->requires_attachment,
            'attachment_after_days' => $this->attachment_after_days,
            'max_days_per_request' => $this->max_days_per_request,
            'notice_days' => $this->notice_days,
            'allows_past_dates' => $this->allows_past_dates,
            'counts_rest_days' => $this->counts_rest_days,
            'counts_holidays' => $this->counts_holidays,
        ];
    }
}
