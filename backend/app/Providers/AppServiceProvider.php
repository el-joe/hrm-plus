<?php

namespace App\Providers;

use App\Enums\AuthArea;
use App\Models\AdminDataScope;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\AnnouncementRead;
use App\Models\AnnouncementTarget;
use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalWorkflow;
use App\Models\ApprovalWorkflowStep;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetEvent;
use App\Models\AttendanceAdjustment;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceDay;
use App\Models\AttendanceDevice;
use App\Models\AttendanceDeviceSyncLog;
use App\Models\AttendanceDeviceUser;
use App\Models\AttendanceManualBatch;
use App\Models\AttendanceManualEntry;
use App\Models\AttendanceMobileAttempt;
use App\Models\AttendancePeriodLock;
use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyAssignment;
use App\Models\AttendancePolicyVersion;
use App\Models\AttendancePunch;
use App\Models\AttendanceRawEvent;
use App\Models\AttendanceStatus;
use App\Models\AttendanceWorkLocationRule;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchLocation;
use App\Models\Candidate;
use App\Models\CandidateAttachment;
use App\Models\CandidateNote;
use App\Models\Company;
use App\Models\Competency;
use App\Models\CostCenter;
use App\Models\DashboardPreference;
use App\Models\Department;
use App\Models\DeviceToken;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryCaseNote;
use App\Models\DisciplinaryEvidence;
use App\Models\Employee;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeContract;
use App\Models\EmployeeCustomField;
use App\Models\EmployeeCustomFieldValue;
use App\Models\EmployeeDocument;
use App\Models\EmployeeEmergencyContact;
use App\Models\EmployeeGroup;
use App\Models\EmployeeLoan;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSkill;
use App\Models\EmploymentType;
use App\Models\EvaluationForm;
use App\Models\FaceConsent;
use App\Models\FaceEnrolment;
use App\Models\FaceVerification;
use App\Models\Holiday;
use App\Models\HrLetterTemplate;
use App\Models\HrProfileChange;
use App\Models\HrRequest;
use App\Models\HrRequestAttachment;
use App\Models\HrRequestCategory;
use App\Models\HrRequestCategoryField;
use App\Models\HrRequestEvent;
use App\Models\HrRequestFieldValue;
use App\Models\HrRequestMessage;
use App\Models\Interview;
use App\Models\InterviewScorecard;
use App\Models\JobApplication;
use App\Models\JobGrade;
use App\Models\JobOffer;
use App\Models\JobTitle;
use App\Models\LeaveBalanceTransaction;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\LoanChange;
use App\Models\LoanInstalment;
use App\Models\LoginHistory;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\NotificationTemplate;
use App\Models\OffboardingCase;
use App\Models\OffboardingItem;
use App\Models\Onboarding;
use App\Models\OnboardingTask;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateTask;
use App\Models\PayEntry;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollItemLine;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\PayslipPayment;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleCompetency;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalUpdate;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCompetency;
use App\Models\Position;
use App\Models\RatingScale;
use App\Models\RatingScaleLevel;
use App\Models\RecruitmentStage;
use App\Models\ReportExport;
use App\Models\Role;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureAssignment;
use App\Models\SalaryStructureVersion;
use App\Models\ScheduleAssignment;
use App\Models\ScheduleChangeRequest;
use App\Models\ScheduleOverride;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\ShiftBreak;
use App\Models\Skill;
use App\Models\StatutoryRuleSet;
use App\Models\StatutoryRuleVersion;
use App\Models\Team;
use App\Models\TrainingAttendance;
use App\Models\TrainingBudget;
use App\Models\TrainingCertificate;
use App\Models\TrainingCourse;
use App\Models\TrainingCourseSkill;
use App\Models\TrainingEnrolment;
use App\Models\TrainingProgramme;
use App\Models\TrainingProvider;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\ViolationType;
use App\Models\WorkSchedule;
use App\Models\WorkScheduleDay;
use App\Policies\AdminUserPolicy;
use App\Services\Attendance\Contracts\LeaveProvider;
use App\Services\Authorization\DataScopeManager;
use App\Services\Leave\ApprovedLeaveProvider;
use App\Services\Notifications\NotificationInbox;
use App\Services\Settings\RuntimeSettings;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One instance per request (and per queued job): it carries the admin
        // whose data scopes are currently being enforced.
        $this->app->scoped(DataScopeManager::class);

        // Approved leave reaches the attendance engine through this hook (Phase 6).
        $this->app->bind(LeaveProvider::class, ApprovedLeaveProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMorphMap();
        // Session lifetime and (once configured) the mail transport come from system settings (Phase 8b).
        app(RuntimeSettings::class)->apply();
        $this->configureAuthorization();
        $this->configureRateLimiting();
        $this->configurePasswords();
        $this->configureViews();
    }

    /**
     * Polymorphic columns (audit_logs.auditable_type, approval_requests.approvable_type,
     * model_has_roles.model_type, ...) store these short aliases instead of class
     * names, so renaming a class never orphans data. Every model that is audited,
     * approved or otherwise used polymorphically MUST be added here; an unmapped
     * model fails loudly.
     */
    private function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'user' => User::class,
            'role' => Role::class,
            'admin_data_scope' => AdminDataScope::class,
            'login_history' => LoginHistory::class,
            'audit_log' => AuditLog::class,
            'setting' => Setting::class,
            'approval_workflow' => ApprovalWorkflow::class,
            'approval_workflow_step' => ApprovalWorkflowStep::class,
            'approval_request' => ApprovalRequest::class,
            'approval_action' => ApprovalAction::class,
            'company' => Company::class,
            'branch' => Branch::class,
            'branch_location' => BranchLocation::class,
            'department' => Department::class,
            'team' => Team::class,
            'position' => Position::class,
            'job_title' => JobTitle::class,
            'job_grade' => JobGrade::class,
            'employment_type' => EmploymentType::class,
            'cost_center' => CostCenter::class,
            'employee' => Employee::class,
            'employee_group' => EmployeeGroup::class,
            'employee_assignment' => EmployeeAssignment::class,
            'employee_salary' => EmployeeSalary::class,
            'employee_bank_account' => EmployeeBankAccount::class,
            'employee_emergency_contact' => EmployeeEmergencyContact::class,
            'employee_contract' => EmployeeContract::class,
            'employee_document' => EmployeeDocument::class,
            'employee_custom_field' => EmployeeCustomField::class,
            'employee_custom_field_value' => EmployeeCustomFieldValue::class,
            'vacancy' => Vacancy::class,
            'recruitment_stage' => RecruitmentStage::class,
            'candidate' => Candidate::class,
            'candidate_note' => CandidateNote::class,
            'candidate_attachment' => CandidateAttachment::class,
            'job_application' => JobApplication::class,
            'evaluation_form' => EvaluationForm::class,
            'interview' => Interview::class,
            'interview_scorecard' => InterviewScorecard::class,
            'job_offer' => JobOffer::class,
            'onboarding_template' => OnboardingTemplate::class,
            'onboarding_template_task' => OnboardingTemplateTask::class,
            'onboarding' => Onboarding::class,
            'onboarding_task' => OnboardingTask::class,
            'offboarding_case' => OffboardingCase::class,
            'offboarding_item' => OffboardingItem::class,
            'shift' => Shift::class,
            'shift_break' => ShiftBreak::class,
            'work_schedule' => WorkSchedule::class,
            'work_schedule_day' => WorkScheduleDay::class,
            'schedule_assignment' => ScheduleAssignment::class,
            'schedule_change_request' => ScheduleChangeRequest::class,
            'schedule_override' => ScheduleOverride::class,
            'holiday' => Holiday::class,
            'attendance_status' => AttendanceStatus::class,
            'attendance_policy' => AttendancePolicy::class,
            'attendance_policy_version' => AttendancePolicyVersion::class,
            'attendance_policy_assignment' => AttendancePolicyAssignment::class,
            'attendance_period_lock' => AttendancePeriodLock::class,
            'attendance_manual_batch' => AttendanceManualBatch::class,
            'attendance_manual_entry' => AttendanceManualEntry::class,
            'attendance_correction' => AttendanceCorrection::class,
            'attendance_raw_event' => AttendanceRawEvent::class,
            'attendance_day' => AttendanceDay::class,
            'attendance_punch' => AttendancePunch::class,
            'attendance_adjustment' => AttendanceAdjustment::class,
            'attendance_device' => AttendanceDevice::class,
            'attendance_device_user' => AttendanceDeviceUser::class,
            'attendance_device_sync_log' => AttendanceDeviceSyncLog::class,
            'attendance_work_location_rule' => AttendanceWorkLocationRule::class,
            'attendance_mobile_attempt' => AttendanceMobileAttempt::class,
            'face_consent' => FaceConsent::class,
            'face_enrolment' => FaceEnrolment::class,
            'face_verification' => FaceVerification::class,
            'leave_type' => LeaveType::class,
            'leave_policy' => LeavePolicy::class,
            'leave_request' => LeaveRequest::class,
            'leave_balance_transaction' => LeaveBalanceTransaction::class,
            'salary_component' => SalaryComponent::class,
            'salary_structure' => SalaryStructure::class,
            'salary_structure_version' => SalaryStructureVersion::class,
            'salary_structure_assignment' => SalaryStructureAssignment::class,
            'pay_entry' => PayEntry::class,
            'statutory_rule_set' => StatutoryRuleSet::class,
            'statutory_rule_version' => StatutoryRuleVersion::class,
            'employee_loan' => EmployeeLoan::class,
            'loan_change' => LoanChange::class,
            'loan_instalment' => LoanInstalment::class,
            'payroll_period' => PayrollPeriod::class,
            'payroll_run' => PayrollRun::class,
            'payroll_item' => PayrollItem::class,
            'payroll_item_line' => PayrollItemLine::class,
            'payroll_adjustment' => PayrollAdjustment::class,
            'payslip' => Payslip::class,
            'payslip_payment' => PayslipPayment::class,
            // Phase 8a: performance, training, assets, disciplinary.
            'rating_scale' => RatingScale::class,
            'rating_scale_level' => RatingScaleLevel::class,
            'competency' => Competency::class,
            'performance_cycle' => PerformanceCycle::class,
            'performance_cycle_competency' => PerformanceCycleCompetency::class,
            'performance_review' => PerformanceReview::class,
            'performance_goal' => PerformanceGoal::class,
            'performance_goal_update' => PerformanceGoalUpdate::class,
            'performance_review_competency' => PerformanceReviewCompetency::class,
            'training_provider' => TrainingProvider::class,
            'training_programme' => TrainingProgramme::class,
            'skill' => Skill::class,
            'training_course' => TrainingCourse::class,
            'training_course_skill' => TrainingCourseSkill::class,
            'training_session' => TrainingSession::class,
            'training_enrolment' => TrainingEnrolment::class,
            'training_attendance' => TrainingAttendance::class,
            'training_certificate' => TrainingCertificate::class,
            'employee_skill' => EmployeeSkill::class,
            'training_budget' => TrainingBudget::class,
            'asset_category' => AssetCategory::class,
            'asset' => Asset::class,
            'asset_assignment' => AssetAssignment::class,
            'asset_event' => AssetEvent::class,
            'violation_type' => ViolationType::class,
            'disciplinary_case' => DisciplinaryCase::class,
            'disciplinary_case_note' => DisciplinaryCaseNote::class,
            'disciplinary_evidence' => DisciplinaryEvidence::class,
            'disciplinary_action' => DisciplinaryAction::class,
            // Phase 8b: HR requests, announcements, notification centre.
            'hr_request_category' => HrRequestCategory::class,
            'hr_request_category_field' => HrRequestCategoryField::class,
            'hr_letter_template' => HrLetterTemplate::class,
            'hr_request' => HrRequest::class,
            'hr_request_message' => HrRequestMessage::class,
            'hr_request_attachment' => HrRequestAttachment::class,
            'hr_request_field_value' => HrRequestFieldValue::class,
            'hr_request_event' => HrRequestEvent::class,
            'hr_profile_change' => HrProfileChange::class,
            'announcement' => Announcement::class,
            'announcement_target' => AnnouncementTarget::class,
            'announcement_attachment' => AnnouncementAttachment::class,
            'announcement_read' => AnnouncementRead::class,
            'notification_template' => NotificationTemplate::class,
            'notification_preference' => NotificationPreference::class,
            'notification_delivery' => NotificationDelivery::class,
            'device_token' => DeviceToken::class,
            'report_export' => ReportExport::class,
            'dashboard_preference' => DashboardPreference::class,
        ]);
    }

    private function configureAuthorization(): void
    {
        // Admin accounts are Users, so the policy cannot be auto-discovered by name.
        Gate::policy(User::class, AdminUserPolicy::class);

        // A Super Admin holds every "module.action" permission, including ones
        // added to the catalogue after the last seed. Policy abilities
        // (update, delete, ...) are NOT short-circuited, so record-level rules
        // such as "nobody deletes their own account" still apply to them.
        // {admin_user} only ever resolves Admin accounts; employee ids are a 404.
        Route::bind('admin_user', fn (string $value): User => User::query()->admins()->findOrFail($value));
        // Portal {assignedInterview}: only interviews the signed-in user sits on; any other id is a 404 (Phase 9).
        // Bindings run before the "portal" middleware picks the guard, so both portal guards are asked here.
        Route::bind('assignedInterview', function (string $value): Interview {
            $user = Auth::guard(AuthArea::Portal->guard())->user() ?? Auth::guard(AuthArea::Admin->guard())->user();
            abort_unless($user instanceof User, 404);

            return Interview::query()->assignedTo($user)->findOrFail($value);
        });

        // API documentation (/docs/api, /docs/api.json, dedoc/scramble): open in the local environment,
        // elsewhere only to a Super Admin signed in to the admin area (Phase 10).
        Gate::define('viewApiDocs', fn (?User $user = null): bool => $user !== null && $user->isAdmin() && $user->isSuperAdmin());

        // Developer component showcase (/admin/ui-kit). Not a catalogue permission:
        // it is not a business capability and must not be grantable to roles.
        Gate::define('viewUiKit', fn (User $user): bool => $user->isAdmin() && $user->isSuperAdmin());

        // Portal navigation: shown to employees who have an onboarding / sit on interviews.
        Gate::define('viewOwnOnboarding', fn (User $user): bool => $user->employee_id !== null
            && Onboarding::query()->withoutGlobalScopes()->where('employee_id', $user->employee_id)->exists());
        Gate::define('viewOwnInterviews', fn (User $user): bool => Interview::query()->withoutGlobalScopes()->assignedTo($user)->exists());

        // Attendance reports (Phase 5b): attendance staff and report viewers.
        Gate::define('viewAttendanceReports', fn (User $user): bool => $user->isAdmin() && ($user->can('attendance.view') || $user->can('reports.view')));
        Gate::define('exportAttendanceReports', fn (User $user): bool => $user->isAdmin() && ($user->can('attendance.export') || $user->can('reports.export')));

        // Leave reports (Phase 6): leave staff and report viewers.
        Gate::define('viewLeaveReports', fn (User $user): bool => $user->isAdmin() && ($user->can('leaves.view') || $user->can('reports.view')));
        Gate::define('exportLeaveReports', fn (User $user): bool => $user->isAdmin() && ($user->can('leaves.export') || $user->can('reports.export')));

        // Disciplinary (Phase 8a): case files need disciplinary.view (restricted); holders of
        // disciplinary.create without it may report incidents and follow the cases they reported.
        Gate::define('accessDisciplinary', fn (User $user): bool => $user->isAdmin() && ($user->can('disciplinary.view') || $user->can('disciplinary.create')));
        // Portal navigation: employees who were issued a disciplinary action.
        Gate::define('viewOwnDisciplinary', fn (User $user): bool => $user->employee_id !== null
            && DisciplinaryAction::query()->withoutGlobalScopes()->where('employee_id', $user->employee_id)->whereNotNull('issued_at')->exists());

        // Reports centre and dashboard customisation (Phase 8c).
        Gate::define('viewReportsCentre', fn (User $user): bool => $user->isAdmin() && $user->can('reports.view'));
        Gate::define('exportReportsCentre', fn (User $user): bool => $user->isAdmin() && $user->can('reports.view') && $user->can('reports.export'));

        Gate::before(function (User $user, string $ability): ?bool {
            return str_contains($ability, '.') && $user->isSuperAdmin() ? true : null;
        });
    }

    private function configureRateLimiting(): void
    {
        // Per token owner when the token is valid, otherwise per IP. The user is
        // resolved through the sanctum guard because throttling runs before auth.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user('sanctum')?->getAuthIdentifier() ?: $request->ip()));

        RateLimiter::for('api-login', fn (Request $request): Limit => Limit::perMinute(10)
            ->by(Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip()));

        // Mobile check-in / check-out / face enrolment, per employee account.
        RateLimiter::for('attendance-checkin', fn (Request $request): Limit => Limit::perMinute(12)
            ->by('checkin|'.($request->user('sanctum')?->getAuthIdentifier() ?: $request->ip())));

        // Device push, per device serial (and per IP for unknown serials).
        RateLimiter::for('device-push', fn (Request $request): Limit => Limit::perMinute((int) config('attendance_devices.push_per_minute'))
            ->by('device|'.$request->route('serial').'|'.$request->ip()));

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perMinute(5)->by($request->ip()));

        // Mobile app reset codes (Phase 10): sending is limited per address and per IP; checking a code
        // (verify / reset) and changing the password per address (or account) and IP. Wrong codes are
        // also counted on the code itself (App\Services\Auth\PasswordResetCodes).
        RateLimiter::for('password-code', fn (Request $request): array => [
            Limit::perMinute(3)->by('code-send|'.Str::lower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perHour(10)->by('code-send-ip|'.$request->ip()),
        ]);
        RateLimiter::for('password-code-check', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('code-check|'.($request->user('sanctum')?->getAuthIdentifier() ?: Str::lower((string) $request->input('email'))).'|'.$request->ip()));

        // Public career page applications, per IP (setting recruitment.career_applications_per_hour).
        RateLimiter::for('career-apply', fn (Request $request): Limit => Limit::perHour(max(1, (int) setting('recruitment.career_applications_per_hour')))
            ->by($request->ip()));
    }

    private function configurePasswords(): void
    {
        Password::defaults(function (): Password {
            $rule = Password::min((int) setting('security.password_min_length'))->letters()->numbers();

            if (setting('security.password_require_mixed_case')) {
                $rule->mixedCase();
            }

            if (setting('security.password_require_symbols')) {
                $rule->symbols();
            }

            return $rule;
        });

        // Send each account to the reset form of its own area.
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => route(
            $user->isAdmin() ? 'admin.password.reset' : 'portal.password.reset',
            ['token' => $token, 'email' => $user->email],
        ));
    }

    /**
     * Navigation of the admin sidebar and the portal menu, filtered by the
     * signed-in user's permissions (see config/navigation.php).
     */
    private function configureViews(): void
    {
        // Display formatting from the localisation settings (Phase 8c, App\Support\Formatter).
        Blade::directive('fmtDate', fn (string $expression): string => "<?php echo e(\\App\\Support\\Formatter::date({$expression})); ?>");
        Blade::directive('fmtDateTime', fn (string $expression): string => "<?php echo e(\\App\\Support\\Formatter::dateTime({$expression})); ?>");
        Blade::directive('fmtNumber', fn (string $expression): string => "<?php echo e(\\App\\Support\\Formatter::number({$expression})); ?>");
        Blade::directive('fmtMoney', fn (string $expression): string => "<?php echo e(\\App\\Support\\Formatter::money({$expression})); ?>");
        Blade::directive('fmtDigits', fn (string $expression): string => "<?php echo e(\\App\\Support\\Formatter::digits({$expression})); ?>");

        View::composer('layouts.admin', function (ViewContract $view): void {
            $view->with('navigation', app(NavigationBuilder::class)->admin(request()->user(), Route::currentRouteName()));
        });

        View::composer('layouts.employee', function (ViewContract $view): void {
            $view->with('navigation', app(NavigationBuilder::class)->portal(request()->user(), Route::currentRouteName()));
        });

        // Notification bell (Phase 8b): the signed-in user's unread count and latest notifications.
        View::composer('partials.notification-bell', function (ViewContract $view): void {
            $user = request()->user();
            $inbox = app(NotificationInbox::class);

            $view->with([
                'bellUnread' => $user ? $inbox->unreadCount($user) : 0,
                'bellItems' => $user ? $inbox->latest($user, 6) : collect(),
            ]);
        });
    }
}
