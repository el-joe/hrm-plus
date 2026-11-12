<?php

namespace App\Http\Controllers\Admin\Scheduling;

use App\Actions\Attendance\SaveAttendancePolicy;
use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Enums\AttendanceScope;
use App\Enums\BreakMode;
use App\Enums\FaceVerificationMode;
use App\Enums\MissingPunchAction;
use App\Enums\MobileRuleAction;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Scheduling\AttendancePolicyRequest;
use App\Http\Requests\Admin\Scheduling\PolicyAssignmentRequest;
use App\Http\Requests\Admin\Scheduling\PolicyVersionRequest;
use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyAssignment;
use App\Models\AttendancePolicyVersion;
use App\Services\Attendance\AttendancePolicyResolver;
use App\Support\IndexSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Attendance policies: identity, append-only rule versions and assignments.
 */
class AttendancePolicyController extends StructureController
{
    /**
     * Rule fields by form section.
     *
     * @var array<string, list<string>>
     */
    public const SECTIONS = [
        'lateness' => ['late_grace_minutes', 'late_count_from_shift_start', 'late_rounding_minutes', 'late_absent_after_minutes'],
        'early' => ['early_leave_grace_minutes', 'early_leave_rounding_minutes'],
        'absence' => ['min_worked_minutes_for_present', 'missing_punch_action', 'punch_dedupe_seconds'],
        'overtime' => ['overtime_enabled', 'overtime_min_minutes', 'overtime_rounding_minutes', 'overtime_max_daily_minutes', 'overtime_requires_approval',
            'overtime_count_early_arrival', 'rest_day_work_is_overtime'],
        'rates' => ['overtime_rate_regular', 'overtime_rate_rest_day', 'overtime_rate_holiday'],
        'breaks' => ['break_mode', 'max_break_minutes'],
        'approvals' => ['manual_entry_requires_approval', 'correction_requires_approval'],
        'mobile' => ['mobile_checkin_enabled', 'gps_max_accuracy_meters', 'gps_accuracy_tolerance_meters', 'gps_checkout_extra_meters',
            'gps_max_time_drift_seconds', 'gps_mock_location_action', 'gps_outside_window_action', 'gps_repeated_attempts_threshold'],
        'web' => ['web_checkin_enabled', 'web_checkin_requires_geofence'],
        'face' => ['face_verification_mode', 'face_max_attempts', 'face_failure_action'],
    ];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AttendancePolicy::class);

        $filters = $this->filters($request);
        $query = $this->applyFilters(AttendancePolicy::query()->withCount(['versions', 'assignments']), $filters);
        $sort = IndexSorting::apply($query, $request, ['code' => 'code', 'name' => AttendancePolicy::localizedColumn()], default: 'code');

        return view('admin.scheduling.policies.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
        ]);
    }

    public function create(AttendancePolicyResolver $resolver): View
    {
        Gate::authorize('create', AttendancePolicy::class);

        return view('admin.scheduling.policies.form', [
            'record' => new AttendancePolicy(['is_active' => true]),
            'rules' => $resolver->defaults(),
            'effectiveFrom' => today()->format('Y-m-d'),
            ...$this->ruleOptions(),
        ]);
    }

    public function store(AttendancePolicyRequest $request, SaveAttendancePolicy $save): RedirectResponse
    {
        $policy = $save->create($request->identityData(), $request->ruleValues(), $request->input('effective_from'), $request->user());

        return redirect()->route('admin.attendance-policies.show', $policy)->with('status', __('structure.messages.created', ['name' => $policy->name]));
    }

    public function show(AttendancePolicy $attendancePolicy): View
    {
        Gate::authorize('view', $attendancePolicy);

        $attendancePolicy->load(['versions', 'assignments']);
        $types = AttendanceScope::forPolicies();

        return view('admin.scheduling.policies.show', [
            'record' => $attendancePolicy,
            'sections' => self::SECTIONS,
            'types' => $types,
            'scopeOptions' => ScopeOptions::for($types),
            'today' => today()->format('Y-m-d'),
        ]);
    }

    public function edit(AttendancePolicy $attendancePolicy): View
    {
        Gate::authorize('update', $attendancePolicy);

        return view('admin.scheduling.policies.form', ['record' => $attendancePolicy, 'rules' => null, 'effectiveFrom' => null, ...$this->ruleOptions()]);
    }

    public function update(AttendancePolicyRequest $request, AttendancePolicy $attendancePolicy): RedirectResponse
    {
        $attendancePolicy->update($request->identityData());

        return redirect()->route('admin.attendance-policies.show', $attendancePolicy)->with('status', __('structure.messages.updated', ['name' => $attendancePolicy->name]));
    }

    public function destroy(AttendancePolicy $attendancePolicy, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($attendancePolicy, $delete, 'admin.attendance-policies.index');
    }

    public function status(Request $request, AttendancePolicy $attendancePolicy, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $attendancePolicy, $changeStatus);
    }

    public function createVersion(AttendancePolicy $attendancePolicy): View
    {
        Gate::authorize('update', $attendancePolicy);

        $latest = $attendancePolicy->versions()->first();

        return view('admin.scheduling.policies.version', [
            'record' => $attendancePolicy,
            'rules' => $latest ?? app(AttendancePolicyResolver::class)->defaults(),
            'latest' => $latest,
            'effectiveFrom' => $latest && $latest->effective_from->gte(today()) ? $latest->effective_from->addDay()->format('Y-m-d') : today()->format('Y-m-d'),
            ...$this->ruleOptions(),
        ]);
    }

    public function storeVersion(PolicyVersionRequest $request, AttendancePolicy $attendancePolicy, SaveAttendancePolicy $save): RedirectResponse
    {
        $version = $save->addVersion($attendancePolicy, $request->ruleValues(), $request->input('effective_from'), $request->user());

        return redirect()->route('admin.attendance-policies.show', $attendancePolicy)->with('status', __('schedules.policies.version_saved', ['n' => $version->version]));
    }

    public function storeAssignment(PolicyAssignmentRequest $request, AttendancePolicy $attendancePolicy): RedirectResponse
    {
        $attendancePolicy->assignments()->create([...$request->assignmentData(), 'created_by' => $request->user()->id]);

        return redirect()->route('admin.attendance-policies.show', $attendancePolicy)->with('status', __('schedules.policies.assignment_added'));
    }

    public function destroyAssignment(AttendancePolicy $attendancePolicy, AttendancePolicyAssignment $assignment): RedirectResponse
    {
        Gate::authorize('update', $attendancePolicy);

        $assignment->delete();

        return redirect()->route('admin.attendance-policies.show', $attendancePolicy)->with('status', __('schedules.policies.assignment_removed'));
    }

    /**
     * @return array<string, mixed>
     */
    private function ruleOptions(): array
    {
        return [
            'sections' => self::SECTIONS,
            'missingPunchActions' => MissingPunchAction::cases(),
            'breakModes' => BreakMode::cases(),
            'enumOptions' => [
                'missing_punch_action' => MissingPunchAction::cases(),
                'break_mode' => BreakMode::cases(),
                'gps_mock_location_action' => MobileRuleAction::cases(),
                'gps_outside_window_action' => MobileRuleAction::cases(),
                'face_failure_action' => MobileRuleAction::cases(),
                'face_verification_mode' => FaceVerificationMode::cases(),
            ],
            'booleans' => AttendancePolicyRequest::$booleanRules,
            'decimals' => ['overtime_rate_regular', 'overtime_rate_rest_day', 'overtime_rate_holiday'],
        ];
    }

    /**
     * Display value of a rule.
     */
    public static function display(AttendancePolicyVersion $version, string $rule): string
    {
        $value = $version->getAttribute($rule);

        return match (true) {
            $value instanceof MissingPunchAction, $value instanceof BreakMode, $value instanceof MobileRuleAction, $value instanceof FaceVerificationMode => $value->label(),
            is_bool($value) => $value ? __('schedules.common.yes') : __('schedules.common.no'),
            $value === null => '—',
            default => (string) $value,
        };
    }
}
