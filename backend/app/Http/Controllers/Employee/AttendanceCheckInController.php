<?php

namespace App\Http\Controllers\Employee;

use App\Enums\PunchDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\PortalCheckInRequest;
use App\Models\Employee;
use App\Services\Attendance\Mobile\MobileCheckIn;
use App\Services\Employees\PortalProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /portal/attendance/check-in    browser geolocation (+ camera face) check-in
 * POST /portal/attendance/check-out   same
 *
 * The web twin of /api/v1/me/attendance/check-in|check-out: the same service
 * (MobileCheckIn, channel "web") and the same JSON envelope and statuses —
 * 201 accepted (recorded) | 202 flagged (sent for review, NOT recorded) |
 * 422 rejected (code "attendance_rejected", data.reasons, data.face_remaining_attempts).
 * Always the signed-in user's own employee record.
 */
class AttendanceCheckInController extends Controller
{
    public function __construct(private readonly PortalProfile $profile) {}

    public function checkIn(PortalCheckInRequest $request, MobileCheckIn $checkIn): JsonResponse
    {
        return $checkIn->handle($this->employee($request), $request->user(), $request->toData(PunchDirection::In))->toResponse();
    }

    public function checkOut(PortalCheckInRequest $request, MobileCheckIn $checkIn): JsonResponse
    {
        return $checkIn->handle($this->employee($request), $request->user(), $request->toData(PunchDirection::Out))->toResponse();
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
