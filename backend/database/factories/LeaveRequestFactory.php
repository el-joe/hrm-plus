<?php

namespace Database\Factories;

use App\Enums\LeaveDayPart;
use App\Enums\LeaveRequestSource;
use App\Enums\LeaveRequestStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending one-day request filed by HR, without request days or an approval. Tests of the
 * leave workflow should go through App\Actions\Leave\ManageLeaveRequest (see fileLeave()).
 *
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d');

        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => $date,
            'end_date' => $date,
            'day_part' => LeaveDayPart::Full,
            'days' => '1.00',
            'reason' => fake()->sentence(),
            'status' => LeaveRequestStatus::Pending,
            'source' => LeaveRequestSource::Admin,
        ];
    }
}
