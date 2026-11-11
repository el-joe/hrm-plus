<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attendance\ManageAttendanceCorrection;
use App\Enums\CorrectionSource;
use App\Exceptions\ApprovalException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Attendance\OwnCorrectionRequest;
use App\Http\Resources\V1\AttendanceCorrectionResource;
use App\Http\Resources\V1\AttendanceDayResource;
use App\Models\Employee;
use App\Services\Attendance\AttendanceTimesheet;
use App\Services\Attendance\BulkAttendance;
use App\Services\Attendance\Data\ResolvedSchedule;
use App\Services\Attendance\Data\ScheduledShift;
use App\Services\Attendance\PortalAttendance;
use App\Services\Employees\PortalProfile;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * GET  /api/v1/me/schedule?from=&to=                 expected shifts per date (max 62 days; default the next 14)
 * GET  /api/v1/me/attendance?month=YYYY-MM            processed days + month summary
 * GET  /api/v1/me/attendance/corrections              own correction requests
 * POST /api/v1/me/attendance/corrections              request a correction (goes to approval)
 *
 * Always the token owner's own employee record.
 */
class AttendanceController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalAttendance $attendance) {}

    public function schedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = CarbonImmutable::parse($data['from'] ?? BulkAttendance::today()->format('Y-m-d'));
        $to = CarbonImmutable::parse($data['to'] ?? $from->addDays(13)->format('Y-m-d'));

        if ($from->diffInDays($to) > 61) {
            throw ValidationException::withMessages(['to' => __('attendance.api.range_too_long', ['days' => 62])]);
        }

        $days = array_values(array_map(fn (ResolvedSchedule $day): array => [
            'date' => $day->date->format('Y-m-d'),
            'timezone' => $day->timezone,
            'is_working_day' => $day->isWorkingDay(),
            'is_rest_day' => $day->isRestDay,
            'employed' => $day->isEmployed(),
            'holiday' => $day->holiday ? ['name' => $day->holiday->name, 'is_paid' => $day->holiday->is_paid] : null,
            'shifts' => array_map(fn (ScheduledShift $shift): array => [
                'id' => $shift->shift->id,
                'code' => $shift->shift->code,
                'name' => $shift->shift->name,
                'type' => $shift->shift->type->value,
                'start_at' => $shift->startAt->toIso8601ZuluString(),
                'end_at' => $shift->endAt->toIso8601ZuluString(),
                'start_local' => $shift->startAt->setTimezone($day->timezone)->format('H:i'),
                'end_local' => $shift->endAt->setTimezone($day->timezone)->format('H:i'),
                'check_in_opens_at' => $shift->windowStart->toIso8601ZuluString(),
                'check_out_closes_at' => $shift->windowEnd->toIso8601ZuluString(),
                'expected_minutes' => $shift->expectedMinutes,
                'is_night' => $shift->shift->is_night,
                'crosses_midnight' => $shift->shift->crossesMidnight(),
            ], $day->shifts),
        ], $this->attendance->schedule($this->employee($request), $from->format('Y-m-d'), $to->format('Y-m-d'))));

        return ApiResponse::success($days);
    }

    public function attendance(Request $request, AttendanceTimesheet $timesheet): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        [$from, $to] = AttendanceTimesheet::month($data['month'] ?? null);
        $days = $timesheet->days($this->employee($request), $from, $to);

        return ApiResponse::success(AttendanceDayResource::collection($days)->resolve(), meta: [
            'month' => $from->format('Y-m'),
            'summary' => $timesheet->summary($days),
        ]);
    }

    public function corrections(Request $request): JsonResponse
    {
        return ApiResponse::success(AttendanceCorrectionResource::collection($this->attendance->corrections($this->employee($request), 50))->resolve());
    }

    public function storeCorrection(OwnCorrectionRequest $request, ManageAttendanceCorrection $corrections): JsonResponse
    {
        try {
            $correction = $corrections->create($this->employee($request), $request->validated(), CorrectionSource::Api, $request->user());
        } catch (ApprovalException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        return ApiResponse::success(new AttendanceCorrectionResource($correction->load('attendanceStatus')), __('attendance.portal.request_sent'), 201);
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
