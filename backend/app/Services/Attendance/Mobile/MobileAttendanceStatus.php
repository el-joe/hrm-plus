<?php

namespace App\Services\Attendance\Mobile;

use App\Enums\MobileAttemptOutcome;
use App\Enums\PunchDirection;
use App\Enums\ReviewStatus;
use App\Models\AttendanceDay;
use App\Models\AttendanceMobileAttempt;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\AttendancePolicyResolver;
use App\Services\Attendance\Data\ScheduledShift;
use App\Services\Attendance\Face\FaceEnrolmentService;
use App\Services\Attendance\Face\FaceVerifier;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;

/**
 * Everything the app's home screen needs for the attendance action
 * (GET /api/v1/me/attendance/today): today's shifts, current state (from the
 * punches recorded today, any source), allowed locations, the rules the
 * server will apply, face status and attempts waiting for review. Display
 * only — every check is redone on submission.
 */
class MobileAttendanceStatus
{
    public function __construct(
        private readonly ScheduleResolver $schedules,
        private readonly AttendancePolicyResolver $policies,
        private readonly WorkLocationResolver $locations,
        private readonly FaceEnrolmentService $faces,
        private readonly FaceVerifier $verifier,
        private readonly AttendanceClock $clock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function today(Employee $employee): array
    {
        $now = CarbonImmutable::now()->utc();
        $today = AttendanceClock::localDate($now, $this->clock->timezoneForBranch($employee->branch_id));
        $resolved = $this->schedules->for($employee, $today);
        $timezone = $resolved->timezone;
        $policy = $resolved->placement !== null ? $this->policies->for($employee, $resolved->placement, $today) : $this->policies->defaults();
        $allowed = $this->locations->for($employee, $resolved->placement, $today);

        $dayStart = CarbonImmutable::parse($today->format('Y-m-d'), $timezone)->utc();
        $punches = AttendancePunch::query()->where('employee_id', $employee->id)
            ->whereBetween('punched_at', [$dayStart, $dayStart->addDay()])
            ->whereHas('rawEvent', fn ($query) => $query->whereNull('voided_at'))
            ->orderBy('punched_at')->get();
        $last = $punches->last();
        $state = match ($last?->direction) {
            PunchDirection::In => 'checked_in',
            PunchDirection::Out => 'checked_out',
            null => 'not_checked_in',
            default => 'unknown',
        };
        $direction = $state === 'checked_in' ? PunchDirection::Out : PunchDirection::In;
        $windowShift = collect($resolved->shifts)->first(fn (ScheduledShift $shift): bool => $shift->windowContains($now));

        return [
            'date' => $today->format('Y-m-d'),
            'timezone' => $timezone,
            'server_time' => $now->toIso8601ZuluString(),
            'employed' => $resolved->isEmployed(),
            'is_working_day' => $resolved->isWorkingDay(),
            'is_rest_day' => $resolved->isRestDay,
            'holiday' => $resolved->holiday?->name,
            'state' => $state,
            'next_action' => $direction === PunchDirection::In ? 'check_in' : 'check_out',
            'within_shift_window' => $windowShift !== null,
            'shifts' => array_map(fn (ScheduledShift $shift): array => [
                'id' => $shift->shift->id,
                'code' => $shift->shift->code,
                'name' => $shift->shift->name,
                'start_at' => $shift->startAt->toIso8601ZuluString(),
                'end_at' => $shift->endAt->toIso8601ZuluString(),
                'start_local' => $shift->startAt->setTimezone($timezone)->format('H:i'),
                'end_local' => $shift->endAt->setTimezone($timezone)->format('H:i'),
                'check_in_opens_at' => $shift->windowStart->toIso8601ZuluString(),
                'check_out_closes_at' => $shift->windowEnd->toIso8601ZuluString(),
            ], $resolved->shifts),
            'punches' => $punches->map(fn (AttendancePunch $punch): array => [
                'at' => $punch->punched_at->toIso8601ZuluString(),
                'local' => AttendanceClock::localTime($punch->punched_at, $timezone),
                'direction' => $punch->direction->value,
                'source' => $punch->source->value,
            ])->values()->all(),
            'locations' => [
                'allow_anywhere' => $allowed->allowAnywhere,
                'source' => $allowed->source,
                'items' => $allowed->locations->map(fn ($location): array => [
                    'id' => $location->id,
                    'name' => $location->name,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'radius_meters' => $location->radius_meters,
                    'checkout_radius_meters' => $location->radius_meters + $policy->gps_checkout_extra_meters,
                ])->values()->all(),
            ],
            'rules' => [
                'mobile_checkin_enabled' => $policy->mobile_checkin_enabled,
                'max_accuracy_meters' => $policy->gps_max_accuracy_meters,
                'accuracy_tolerance_meters' => $policy->gps_accuracy_tolerance_meters,
                'max_time_drift_seconds' => $policy->gps_max_time_drift_seconds,
                'mock_location_action' => $policy->gps_mock_location_action->value,
                'outside_window_action' => $policy->gps_outside_window_action->value,
            ],
            'face' => [
                'mode' => $policy->face_verification_mode->value,
                'max_attempts' => $policy->face_max_attempts,
                'remaining_attempts' => max(0, $policy->face_max_attempts - $this->verifier->failuresToday($employee, $direction, $today)),
                ...$this->faces->status($employee),
            ],
            'pending_reviews' => AttendanceMobileAttempt::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
                ->where('outcome', MobileAttemptOutcome::Flagged)->where('review_status', ReviewStatus::Pending)->count(),
            'days' => AttendanceDay::query()->withoutGlobalScopes()->with(['shift', 'holiday', 'attendanceStatus'])
                ->where('employee_id', $employee->id)->whereDate('work_date', $today->format('Y-m-d'))->get(),
        ];
    }
}
