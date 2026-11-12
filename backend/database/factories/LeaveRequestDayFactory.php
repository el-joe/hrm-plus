<?php

namespace Database\Factories;

use App\Enums\LeaveDayPart;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The single counted working day of a leave request.
 *
 * @extends Factory<LeaveRequestDay>
 */
class LeaveRequestDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'leave_request_id' => LeaveRequest::factory(),
            'employee_id' => fn (array $attributes) => LeaveRequest::query()->withoutGlobalScopes()->findOrFail($attributes['leave_request_id'])->employee_id,
            'date' => fn (array $attributes) => LeaveRequest::query()->withoutGlobalScopes()->findOrFail($attributes['leave_request_id'])->start_date->toDateString(),
            'leave_year' => fn (array $attributes) => (int) substr($attributes['date'], 0, 4),
            'day_part' => LeaveDayPart::Full,
            'amount' => '1.00',
            'is_counted' => true,
            'day_kind' => 'working',
        ];
    }
}
