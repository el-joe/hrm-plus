<?php

namespace App\Services\Attendance\Data;

/**
 * Approved leave on one date, as reported by a LeaveProvider.
 */
final readonly class LeaveDay
{
    /**
     * @param  string  $reference  e.g. "leave_request:42", stored on the attendance day
     * @param  string  $typeCode  leave type code, for display and payroll
     * @param  bool  $halfDay  half-day leave: the employee is still expected for the rest of the shift
     */
    public function __construct(
        public string $reference,
        public string $typeCode,
        public bool $isPaid = true,
        public bool $halfDay = false,
    ) {}
}
