<?php

namespace App\Http\Resources\V1;

use App\Models\AttendanceCorrection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An attendance correction request of the employee (mobile app).
 *
 * @mixin AttendanceCorrection
 */
class AttendanceCorrectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->work_date->toDateString(),
            'check_in_at' => $this->check_in_at?->toIso8601ZuluString(),
            'check_out_at' => $this->check_out_at?->toIso8601ZuluString(),
            'attendance_status' => $this->attendanceStatus ? ['id' => $this->attendanceStatus->id, 'code' => $this->attendanceStatus->code, 'name' => $this->attendanceStatus->name] : null,
            'reason' => $this->reason,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'decided_at' => $this->decided_at?->toIso8601ZuluString(),
        ];
    }
}
