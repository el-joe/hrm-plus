<?php

namespace App\Services\Attendance\Mobile;

use App\Models\Employee;
use App\Services\Attendance\AttendanceClock;
use Carbon\CarbonImmutable;

/**
 * Display data of the portal check-in / check-out card (Employee Home and My
 * Attendance), from the same status the app reads (MobileAttendanceStatus).
 * Display only: POST /portal/attendance/check-in|check-out goes through
 * MobileCheckIn, which redoes every check.
 *
 * "blocked" is set (and no button is rendered) only when the server would
 * certainly refuse: not employed, web check-in switched off by the policy, no
 * location to check in at, or a required face check the employee cannot pass
 * on the web (not enrolled and failures are rejected).
 */
class PortalCheckIn
{
    public function __construct(private readonly MobileAttendanceStatus $status) {}

    /**
     * @return array<string, mixed>
     */
    public function card(Employee $employee): array
    {
        $today = $this->status->today($employee);
        $rules = $today['rules'];
        $face = $today['face'];
        $timezone = $today['timezone'];
        $requiresLocation = (bool) $rules['web_checkin_requires_geofence'];
        $locations = $today['locations'];
        $faceNeeded = $face['mode'] === 'required';
        $faceOffered = $face['mode'] === 'optional' && $face['enrolled'];
        $now = CarbonImmutable::now()->utc();

        $blocked = match (true) {
            ! $today['employed'] => 'not_employed',
            ! $rules['web_checkin_enabled'] => 'web_disabled',
            $requiresLocation && ! $locations['allow_anywhere'] && $locations['items'] === [] => 'no_location_configured',
            $faceNeeded && ! $face['enrolled'] && $rules['face_failure_action'] === 'reject' => 'face_not_enrolled',
            default => null,
        };

        // The shift whose window is open now, else the next one still to open today.
        $window = collect($today['shifts'])->first(fn (array $shift): bool => CarbonImmutable::parse($shift['check_in_opens_at'])->lte($now)
            && CarbonImmutable::parse($shift['check_out_closes_at'])->gte($now))
            ?? collect($today['shifts'])->first(fn (array $shift): bool => CarbonImmutable::parse($shift['check_in_opens_at'])->gt($now));

        return [
            'state' => $today['state'],
            'next_action' => $today['next_action'],
            'blocked' => $blocked,
            'blocked_message' => $blocked !== null ? __('mobile.web.blocked.'.$blocked) : null,
            'within_shift_window' => $today['within_shift_window'],
            'window' => $window === null ? null : [
                'name' => $window['name'],
                'opens_local' => AttendanceClock::localTime(CarbonImmutable::parse($window['check_in_opens_at']), $timezone),
                'closes_local' => AttendanceClock::localTime(CarbonImmutable::parse($window['check_out_closes_at']), $timezone),
            ],
            'outside_window_action' => $rules['outside_window_action'],
            'requires_location' => $requiresLocation,
            'allow_anywhere' => (bool) $locations['allow_anywhere'],
            'location_names' => array_column($locations['items'], 'name'),
            'max_accuracy_meters' => (int) $rules['max_accuracy_meters'],
            'face' => [
                'mode' => $face['mode'],
                'capture' => $faceNeeded || $faceOffered,
                'optional' => ! $faceNeeded,
                'enrolled' => (bool) $face['enrolled'],
                'remaining_attempts' => (int) $face['remaining_attempts'],
                'failure_action' => $rules['face_failure_action'],
                'is_simulated' => (bool) $face['is_simulated'],
            ],
            'pending_reviews' => $today['pending_reviews'],
        ];
    }
}
