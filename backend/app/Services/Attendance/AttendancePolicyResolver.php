<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceScope;
use App\Enums\BreakMode;
use App\Enums\FaceVerificationMode;
use App\Enums\MissingPunchAction;
use App\Enums\MobileRuleAction;
use App\Models\AttendancePolicyAssignment;
use App\Models\AttendancePolicyVersion;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Services\Settings\SettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The attendance-policy version that applies to an employee on a date.
 *
 * The policy is chosen by its assignment (effective on the date) with the
 * highest precedence: employee > employee group > employment type >
 * department (nearest up the tree) > branch > company; the latest
 * effective_from wins within a level. The policy's version in force on the
 * date is returned. With no assignment (or no version yet) an unsaved
 * version built from the attendance settings is returned (id null), so a
 * fresh installation still calculates with the configured defaults.
 */
class AttendancePolicyResolver
{
    /** @var Collection<int, AttendancePolicyAssignment>|null */
    private ?Collection $assignments = null;

    /** @var array<int, Collection<int, AttendancePolicyVersion>> */
    private array $versions = [];

    /** @var array<int, ?int>|null */
    private ?array $departmentParents = null;

    public function __construct(private readonly SettingsService $settings) {}

    public function for(Employee $employee, EmployeeAssignment $placement, CarbonImmutable $date): AttendancePolicyVersion
    {
        $day = $date->format('Y-m-d');
        $effective = $this->assignments()->filter(fn (AttendancePolicyAssignment $row): bool => $row->effective_from->format('Y-m-d') <= $day
            && ($row->effective_to === null || $row->effective_to->format('Y-m-d') >= $day));

        $groupIds = DB::table('employee_group_members')
            ->join('employee_groups', 'employee_groups.id', '=', 'employee_group_members.employee_group_id')
            ->where('employee_group_members.employee_id', $employee->id)
            ->where('employee_groups.is_active', true)
            ->pluck('employee_group_id')->map(fn ($id): int => (int) $id)->all();

        $candidates = [
            [AttendanceScope::Employee, [$employee->id]],
            [AttendanceScope::EmployeeGroup, $groupIds],
            [AttendanceScope::EmploymentType, [(int) $placement->employment_type_id]],
        ];

        foreach ($this->departmentChain((int) $placement->department_id) as $ancestor) {
            $candidates[] = [AttendanceScope::Department, [$ancestor]];
        }

        $candidates[] = [AttendanceScope::Branch, [(int) $placement->branch_id]];
        $candidates[] = [AttendanceScope::Company, [null]];

        foreach ($candidates as [$scope, $ids]) {
            $match = $effective
                ->filter(fn (AttendancePolicyAssignment $row): bool => $row->scope_type === $scope && in_array($row->scope_id, $ids, true))
                ->sortBy([['effective_from', 'desc'], ['id', 'desc']])
                ->first();

            if ($match !== null && ($version = $this->versionOn($match->attendance_policy_id, $day))) {
                return $version;
            }
        }

        return $this->defaults();
    }

    /**
     * Unsaved rules from the attendance settings (used when no policy applies).
     */
    public function defaults(): AttendancePolicyVersion
    {
        $overtimeRate = (string) $this->settings->get('payroll.overtime_rate_multiplier');

        return new AttendancePolicyVersion([
            'version' => 0,
            'late_grace_minutes' => (int) $this->settings->get('attendance.late_grace_minutes'),
            'late_count_from_shift_start' => true,
            'late_rounding_minutes' => 0,
            'late_absent_after_minutes' => null,
            'early_leave_grace_minutes' => (int) $this->settings->get('attendance.early_leave_grace_minutes'),
            'early_leave_rounding_minutes' => 0,
            'min_worked_minutes_for_present' => null,
            'missing_punch_action' => MissingPunchAction::Incomplete,
            'punch_dedupe_seconds' => 60,
            'overtime_enabled' => true,
            'overtime_min_minutes' => (int) $this->settings->get('attendance.overtime_minimum_minutes'),
            'overtime_rounding_minutes' => 0,
            'overtime_max_daily_minutes' => null,
            'overtime_requires_approval' => true,
            'overtime_count_early_arrival' => false,
            'rest_day_work_is_overtime' => true,
            'overtime_rate_regular' => $overtimeRate,
            'overtime_rate_rest_day' => $overtimeRate,
            'overtime_rate_holiday' => $overtimeRate,
            'break_mode' => BreakMode::Scheduled,
            'max_break_minutes' => null,
            'manual_entry_requires_approval' => (bool) $this->settings->get('attendance.manual_entry_requires_approval'),
            'correction_requires_approval' => true,
            ...$this->mobileDefaults(),
        ]);
    }

    /**
     * Mobile / face rules from the attendance settings (defaults of a new policy version).
     *
     * @return array<string, mixed>
     */
    public function mobileDefaults(): array
    {
        return [
            'mobile_checkin_enabled' => true,
            'gps_max_accuracy_meters' => (int) $this->settings->get('attendance.gps_max_accuracy_meters'),
            'gps_accuracy_tolerance_meters' => (int) $this->settings->get('attendance.gps_accuracy_tolerance_meters'),
            'gps_checkout_extra_meters' => 0,
            'gps_max_time_drift_seconds' => (int) $this->settings->get('attendance.gps_max_time_drift_seconds'),
            'gps_mock_location_action' => MobileRuleAction::Review,
            'gps_outside_window_action' => MobileRuleAction::Review,
            'gps_repeated_attempts_threshold' => (int) $this->settings->get('attendance.gps_repeated_attempts_threshold'),
            'face_verification_mode' => $this->settings->get('attendance.face_verification_required') ? FaceVerificationMode::Required : FaceVerificationMode::Optional,
            'face_max_attempts' => (int) $this->settings->get('attendance.face_verification_max_retries'),
            'face_failure_action' => MobileRuleAction::Review,
            'web_checkin_enabled' => true,
            'web_checkin_requires_geofence' => true,
        ];
    }

    private function versionOn(int $policyId, string $day): ?AttendancePolicyVersion
    {
        $this->versions[$policyId] ??= AttendancePolicyVersion::query()->where('attendance_policy_id', $policyId)
            ->orderByDesc('effective_from')->get();

        return $this->versions[$policyId]->first(fn (AttendancePolicyVersion $version): bool => $version->effective_from->format('Y-m-d') <= $day);
    }

    /**
     * @return Collection<int, AttendancePolicyAssignment>
     */
    private function assignments(): Collection
    {
        return $this->assignments ??= AttendancePolicyAssignment::query()
            ->whereHas('policy', fn ($query) => $query->where('is_active', true))
            ->get();
    }

    /**
     * @return list<int>
     */
    private function departmentChain(int $departmentId): array
    {
        $this->departmentParents ??= Department::query()->withoutGlobalScopes()->pluck('parent_id', 'id')
            ->map(fn ($id): ?int => $id === null ? null : (int) $id)->all();

        $chain = [];
        $current = $departmentId;

        while ($current !== null && ! in_array($current, $chain, true)) {
            $chain[] = $current;
            $current = $this->departmentParents[$current] ?? null;
        }

        return $chain;
    }
}
