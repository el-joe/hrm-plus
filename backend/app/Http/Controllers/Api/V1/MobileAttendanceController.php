<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MobileAttemptOutcome;
use App\Enums\PunchDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\MobileAttendanceRequest;
use App\Http\Resources\V1\AttendanceDayResource;
use App\Http\Resources\V1\MobileAttemptResource;
use App\Models\AttendanceMobileAttempt;
use App\Models\Employee;
use App\Services\Attendance\Mobile\CheckInResult;
use App\Services\Attendance\Mobile\MobileAttendanceStatus;
use App\Services\Attendance\Mobile\MobileCheckIn;
use App\Services\Employees\PortalProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET  /api/v1/me/attendance/today         state of the attendance action (shifts, punches, locations, rules, face)
 * POST /api/v1/me/attendance/check-in      GPS (+ face) check-in      201 accepted | 202 flagged | 422 rejected
 * POST /api/v1/me/attendance/check-out     GPS (+ face) check-out     same
 * GET  /api/v1/me/attendance/attempts      own recent attempts (outcome / review status)
 *
 * The app shows success only for 201. 202 means "sent for review — not
 * recorded yet". 422 with code "attendance_rejected" lists reason codes in
 * data.reasons (and data.face_remaining_attempts for a face retry).
 */
class MobileAttendanceController extends Controller
{
    public function __construct(private readonly PortalProfile $profile) {}

    public function today(Request $request, MobileAttendanceStatus $status): JsonResponse
    {
        $data = $status->today($this->employee($request));
        $data['days'] = AttendanceDayResource::collection($data['days'])->resolve();

        return ApiResponse::success($data);
    }

    public function checkIn(MobileAttendanceRequest $request, MobileCheckIn $checkIn): JsonResponse
    {
        return $this->respond($checkIn->handle($this->employee($request), $request->user(), $request->toData(PunchDirection::In)));
    }

    public function checkOut(MobileAttendanceRequest $request, MobileCheckIn $checkIn): JsonResponse
    {
        return $this->respond($checkIn->handle($this->employee($request), $request->user(), $request->toData(PunchDirection::Out)));
    }

    public function attempts(Request $request): JsonResponse
    {
        $attempts = AttendanceMobileAttempt::query()->withoutGlobalScopes()->with(['location', 'faceVerification'])
            ->where('employee_id', $this->employee($request)->id)->latest('server_time')->latest('id')->paginate(20);

        return ApiResponse::paginated($attempts, MobileAttemptResource::class);
    }

    private function respond(CheckInResult $result): JsonResponse
    {
        $attempt = new MobileAttemptResource($result->attempt);

        return match ($result->attempt->outcome) {
            MobileAttemptOutcome::Accepted => ApiResponse::success($attempt, __('mobile.api.accepted_'.$result->attempt->direction->value), 201),
            MobileAttemptOutcome::Flagged => ApiResponse::success($attempt, __('mobile.api.flagged'), 202),
            MobileAttemptOutcome::Rejected => ApiResponse::error(
                __('mobile.reasons.'.($result->attempt->reasonCodes()[0] ?? 'outside_geofence')),
                422,
                'attendance_rejected',
                data: [...$attempt->resolve(), 'face_remaining_attempts' => $result->remainingFaceAttempts],
            ),
        };
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
