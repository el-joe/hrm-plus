<?php

namespace App\Services\Attendance\Mobile;

use App\Enums\MobileAttemptOutcome;
use App\Enums\MobileRuleAction;
use App\Enums\PunchDirection;
use App\Enums\RawEventSource;
use App\Enums\ReviewStatus;
use App\Models\AttendanceMobileAttempt;
use App\Models\AttendancePolicyVersion;
use App\Models\BranchLocation;
use App\Models\Employee;
use App\Models\FaceVerification;
use App\Models\User;
use App\Services\Attendance\AttendanceClock;
use App\Services\Attendance\AttendancePolicyResolver;
use App\Services\Attendance\Data\RawEventData;
use App\Services\Attendance\Data\ResolvedSchedule;
use App\Services\Attendance\Data\ScheduledShift;
use App\Services\Attendance\Face\FaceVerifier;
use App\Services\Attendance\RawEventRecorder;
use App\Services\Attendance\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Server-side validation of a mobile GPS check-in / check-out. Nothing the
 * app sends is trusted; the punch time is the SERVER time.
 *
 * Checks, with the rules of the employee's attendance-policy version:
 *
 *   employed on the date, mobile check-in enabled          else rejected
 *   GPS accuracy <= gps_max_accuracy_meters                 else rejected (retry outdoors)
 *   distance (haversine) to an allowed location <= its
 *   radius + min(accuracy, gps_accuracy_tolerance_meters)
 *   (+ gps_checkout_extra_meters on check-out)              else rejected
 *   mock-location flag from the app                        gps_mock_location_action: reject | review
 *   inside a scheduled shift's check-in/out window          gps_outside_window_action: reject | review
 *   |device time - server time| <= gps_max_time_drift_seconds   else review
 *   >= gps_repeated_attempts_threshold rejections in the last hour   review
 *   face verification (FaceVerifier, mode off/optional/required)    retry / review / reject
 *
 * Outcome: accepted -> recorded through RawEventRecorder (source mobile_gps,
 * idempotency key "mobile:{request uuid}"); flagged -> stored for the review
 * queue, recorded only if approved; rejected -> stored, nothing recorded.
 * The same request uuid always returns the stored outcome.
 */
class MobileCheckIn
{
    public function __construct(
        private readonly ScheduleResolver $schedules,
        private readonly AttendancePolicyResolver $policies,
        private readonly WorkLocationResolver $locations,
        private readonly FaceVerifier $faces,
        private readonly RawEventRecorder $recorder,
        private readonly AttendanceClock $clock,
    ) {}

    public function handle(Employee $employee, User $user, CheckInData $data): CheckInResult
    {
        if ($existing = $this->replay($employee, $data->requestUuid)) {
            return $existing;
        }

        $now = CarbonImmutable::now()->utc();
        [$resolved, $shift] = $this->context($employee, $now);
        $workDate = $shift !== null ? $resolved->date : AttendanceClock::localDate($now, $resolved->timezone);
        $policy = $resolved->placement !== null ? $this->policies->for($employee, $resolved->placement, $workDate) : $this->policies->defaults();

        $reject = [];
        $review = [];
        $attributes = [
            'request_uuid' => $data->requestUuid, 'employee_id' => $employee->id, 'user_id' => $user->id, 'direction' => $data->direction,
            'latitude' => $data->latitude, 'longitude' => $data->longitude, 'accuracy_meters' => round($data->accuracyMeters, 2),
            'is_mock_location' => $data->isMockLocation, 'device_time' => $data->deviceTime?->utc(), 'server_time' => $now,
            'time_drift_seconds' => $data->deviceTime !== null ? (int) $data->deviceTime->utc()->diffInSeconds($now, false) * -1 : null,
            'device_identifier' => $data->deviceIdentifier, 'platform' => $data->platform, 'app_version' => $data->appVersion,
            'work_date' => $workDate->format('Y-m-d'), 'shift_id' => $shift?->key(), 'within_shift_window' => $shift !== null,
            'timezone' => $resolved->timezone, 'face_mode' => $policy->face_verification_mode, 'ip_address' => $data->ipAddress,
        ];

        if (! $resolved->isEmployed()) {
            $reject[] = AttemptReason::NOT_EMPLOYED;
        } elseif (! $policy->mobile_checkin_enabled) {
            $reject[] = AttemptReason::MOBILE_DISABLED;
        }

        if ($data->accuracyMeters > $policy->gps_max_accuracy_meters) {
            $reject[] = AttemptReason::POOR_ACCURACY;
        }

        $attributes += $this->geofence($employee, $resolved, $workDate, $data, $policy, $reject);

        if ($data->isMockLocation) {
            $this->apply($policy->gps_mock_location_action, AttemptReason::MOCK_LOCATION, $reject, $review);
        }

        if ($shift === null) {
            $this->apply($policy->gps_outside_window_action, AttemptReason::OUTSIDE_SHIFT_WINDOW, $reject, $review);
        }

        if ($attributes['time_drift_seconds'] !== null && abs($attributes['time_drift_seconds']) > $policy->gps_max_time_drift_seconds) {
            $review[] = AttemptReason::TIME_DRIFT;
        }

        if ($this->recentRejections($employee, $now) >= $policy->gps_repeated_attempts_threshold) {
            $review[] = AttemptReason::REPEATED_ATTEMPTS;
        }

        $face = null;
        $remaining = null;

        // The face is checked last, so an attempt refused for its location never uses up a face attempt.
        if ($reject === []) {
            $face = $this->faces->check($employee, $policy->face_verification_mode, $data->face, $policy->face_max_attempts, $data->direction, $workDate);
            $remaining = $face->remainingAttempts;

            match ($face->outcome) {
                'missing' => $reject[] = AttemptReason::FACE_REQUIRED,
                'retry' => $reject[] = AttemptReason::FACE_RETRY,
                'fallback' => $this->apply($policy->face_failure_action,
                    $face->result->value === 'not_enrolled' ? AttemptReason::FACE_NOT_ENROLLED : AttemptReason::FACE_FAILED, $reject, $review),
                default => null,
            };
        }

        $outcome = match (true) {
            $reject !== [] => MobileAttemptOutcome::Rejected,
            $review !== [] => MobileAttemptOutcome::Flagged,
            default => MobileAttemptOutcome::Accepted,
        };

        try {
            $attempt = DB::transaction(function () use ($attributes, $outcome, $reject, $review, $face): AttendanceMobileAttempt {
                $attempt = AttendanceMobileAttempt::query()->create([
                    ...$attributes,
                    'outcome' => $outcome,
                    'review_status' => $outcome === MobileAttemptOutcome::Flagged ? ReviewStatus::Pending : null,
                    'reasons' => array_values(array_unique([...$reject, ...$review])) ?: null,
                ]);

                if ($face?->attributes !== null) {
                    FaceVerification::query()->create([...$face->attributes, 'attendance_mobile_attempt_id' => $attempt->id]);
                }

                return $attempt;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->replay($employee, $data->requestUuid) ?? throw new ConflictHttpException;
        }

        if ($outcome === MobileAttemptOutcome::Accepted) {
            $this->record($attempt, null);
        }

        return new CheckInResult($attempt->fresh(['location', 'faceVerification']), $remaining);
    }

    /**
     * Record the punch of an accepted (or approved) attempt.
     */
    public function record(AttendanceMobileAttempt $attempt, ?User $reviewer): void
    {
        $event = $this->recorder->record(RawEventData::punch(
            RawEventSource::MobileGps, $attempt->employee_id, $attempt->server_time, $attempt->direction, $attempt->timezone,
            'mobile:'.$attempt->request_uuid,
            sourceReference: mb_substr(trim(($attempt->platform ?? 'app').' '.($attempt->device_identifier ?? '')), 0, 191),
            payload: array_filter([
                'latitude' => (float) $attempt->latitude,
                'longitude' => (float) $attempt->longitude,
                'accuracy_meters' => (float) $attempt->accuracy_meters,
                'distance_meters' => $attempt->distance_meters,
                'branch_location_id' => $attempt->branch_location_id,
                'mobile_attempt_id' => $attempt->id,
                'face' => $attempt->faceVerification?->result->value ?? $attempt->face_mode->value,
                'reviewed' => $reviewer !== null ? true : null,
            ], fn ($value) => $value !== null),
            origin: $attempt,
            recordedBy: $reviewer,
        ));

        $attempt->forceFill(['attendance_raw_event_id' => $event->id])->save();
    }

    /**
     * @param  list<string>  $reject
     * @return array<string, mixed>
     */
    private function geofence(Employee $employee, ResolvedSchedule $resolved, CarbonImmutable $workDate, CheckInData $data, AttendancePolicyVersion $policy, array &$reject): array
    {
        $allowed = $this->locations->for($employee, $resolved->placement, $workDate);
        $tolerance = min($data->accuracyMeters, (float) $policy->gps_accuracy_tolerance_meters);
        $extra = $data->direction === PunchDirection::Out ? $policy->gps_checkout_extra_meters : 0;
        $nearest = null;

        foreach ($allowed->locations as $location) {
            $distance = GeoDistance::meters($data->latitude, $data->longitude, (float) $location->latitude, (float) $location->longitude);
            $radius = $location->radius_meters + $tolerance + $extra;

            if ($nearest === null || $distance - $radius < $nearest['distance'] - $nearest['radius']) {
                $nearest = ['location' => $location, 'distance' => $distance, 'radius' => $radius];
            }
        }

        $within = $allowed->allowAnywhere || ($nearest !== null && $nearest['distance'] <= $nearest['radius']);

        if (! $within) {
            $reject[] = $nearest === null ? AttemptReason::NO_LOCATION : AttemptReason::OUTSIDE_GEOFENCE;
        }

        /** @var BranchLocation|null $location */
        $location = $nearest['location'] ?? null;

        return [
            'branch_location_id' => $location?->id,
            'distance_meters' => $nearest !== null ? (int) round($nearest['distance']) : null,
            'allowed_radius_meters' => $nearest !== null ? (int) floor($nearest['radius']) : null,
            'within_geofence' => $within,
            'allow_anywhere' => $allowed->allowAnywhere,
        ];
    }

    /**
     * The schedule and shift the attempt belongs to: a shift of today or
     * yesterday (night shifts) whose check-in / check-out window contains now.
     *
     * @return array{0: ResolvedSchedule, 1: ?ScheduledShift}
     */
    private function context(Employee $employee, CarbonImmutable $now): array
    {
        $today = AttendanceClock::localDate($now, $this->clock->timezoneForBranch($employee->branch_id));
        $resolvedToday = $this->schedules->for($employee, $today);

        foreach ([$resolvedToday, $this->schedules->for($employee, $today->subDay())] as $resolved) {
            foreach ($resolved->shifts as $shift) {
                if ($resolved->holiday === null && $shift->windowContains($now)) {
                    return [$resolved, $shift];
                }
            }
        }

        return [$resolvedToday, null];
    }

    /**
     * @param  list<string>  $reject
     * @param  list<string>  $review
     */
    private function apply(MobileRuleAction $action, string $reason, array &$reject, array &$review): void
    {
        $action === MobileRuleAction::Reject ? $reject[] = $reason : $review[] = $reason;
    }

    private function recentRejections(Employee $employee, CarbonImmutable $now): int
    {
        return AttendanceMobileAttempt::query()->withoutGlobalScopes()->where('employee_id', $employee->id)
            ->where('outcome', MobileAttemptOutcome::Rejected)->where('server_time', '>=', $now->subHour())->count();
    }

    private function replay(Employee $employee, string $uuid): ?CheckInResult
    {
        $attempt = AttendanceMobileAttempt::query()->withoutGlobalScopes()->with(['location', 'faceVerification'])->where('request_uuid', $uuid)->first();

        if ($attempt === null) {
            return null;
        }

        if ($attempt->employee_id !== $employee->id) {
            throw new ConflictHttpException(__('mobile.api.uuid_reused'));
        }

        return new CheckInResult($attempt, replayed: true);
    }
}
