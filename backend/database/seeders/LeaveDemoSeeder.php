<?php

namespace Database\Seeders;

use App\Actions\Leave\ManageLeaveRequest;
use App\Actions\Leave\SaveLeavePolicy;
use App\Enums\Gender;
use App\Enums\LeaveAccrualMethod;
use App\Enums\LeaveDayPart;
use App\Enums\LeavePolicyScope;
use App\Enums\LeaveRequestSource;
use App\Enums\LeaveUnit;
use App\Enums\RawEventKind;
use App\Models\AttendanceRawEvent;
use App\Models\AttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\EmploymentType;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Approvals\ApprovalService;
use App\Services\Attendance\BulkAttendance;
use App\Services\Attendance\RawEventRecorder;
use App\Services\Leave\LeaveAccrualService;
use App\Services\Leave\LeaveRollover;
use App\Services\Leave\LeaveYear;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Demo leave data (never in production), built through the real actions so
 * balances, approvals and attendance are consistent:
 *
 * - DEMO Egyptian-style leave types (annual, sick, casual, unpaid,
 *   maternity, Hajj) — figures are examples, not legal advice; edit them;
 * - policies: annual leave by years of service for the company, a monthly
 *   one for part-time staff, a richer one for the FIELD group; sick leave
 *   with an effective-dated change on 1 January; casual, maternity, Hajj;
 * - balances: last leave year accrued, a few days taken, rolled over
 *   (carry-over with expiry), then this year accrued up to today;
 * - about 40 requests in every state (approved through the two-level
 *   workflow, pending at each level, rejected, withdrawn, cancelled,
 *   cancellation pending), portal requests of employee1–3@;
 * - approved leave in the attendance demo window: the simulated punches of
 *   those days are voided ("employee was on leave") and the days recalculated,
 *   so attendance shows on_leave / sick leave.
 *
 * Runs with the queue on "sync" so recalculation and notifications happen
 * now (mail goes to the log mailer).
 */
class LeaveDemoSeeder extends Seeder
{
    /** @var array<string, LeaveType> */
    private array $types = [];

    /** @var array<string, User> */
    private array $admins = [];

    private int $created = 0;

    private int $skipped = 0;

    public function run(): void
    {
        if (LeaveType::query()->exists()) {
            return;
        }

        config(['queue.default' => 'sync']);

        $this->admins = User::query()->admins()->get()->keyBy('email')->all();
        $this->types();
        $this->policies();

        $years = app(LeaveYear::class);
        $today = BulkAttendance::today();
        $current = $years->of($today);
        $accruals = app(LeaveAccrualService::class);

        // Last leave year: full accrual, some leave taken, then the rollover into this year.
        $accruals->accrueAll($years->end($current - 1));
        $this->lastYear($years->start($current - 1));
        app(LeaveRollover::class)->rollover($current);
        $accruals->accrueAll($today);
        $accruals->expireCarryOver($today);

        $this->requests($today);

        $this->command?->info("Leave demo: {$this->created} requests created, {$this->skipped} skipped by the business rules.");
    }

    private function types(): void
    {
        $sick = AttendanceStatus::query()->where('code', 'sick_leave')->value('id');
        $rows = [
            'ANNUAL' => ['إجازة اعتيادية', 'Annual leave', 'chart-1', LeaveUnit::HalfDay, ['notice_days' => 3, 'min_service_months' => 0, 'max_days_per_request' => 30]],
            'SICK' => ['إجازة مرضية', 'Sick leave', 'chart-5', LeaveUnit::Day, [
                'allows_past_dates' => true, 'requires_attachment' => true, 'attachment_after_days' => 1, 'attendance_status_id' => $sick,
                'approval_workflow' => 'leave_request_hr',
            ]],
            'CASUAL' => ['إجازة عارضة', 'Casual leave', 'chart-3', LeaveUnit::Hour, ['allows_past_dates' => true, 'max_days_per_request' => 2]],
            'UNPAID' => ['إجازة بدون أجر', 'Unpaid leave', 'chart-4', LeaveUnit::Day, [
                'is_paid' => false, 'affects_payroll' => true, 'tracks_balance' => false, 'notice_days' => 7, 'max_days_per_request' => 30,
            ]],
            'MATERNITY' => ['إجازة وضع', 'Maternity leave', 'chart-6', LeaveUnit::Day, [
                'gender' => Gender::Female, 'min_service_months' => 10, 'max_times_per_service' => 3, 'counts_rest_days' => true, 'counts_holidays' => true,
                'requires_attachment' => true, 'max_days_per_request' => 90, 'approval_workflow' => 'leave_request_hr',
            ]],
            'HAJJ' => ['إجازة حج', 'Hajj leave', 'chart-2', LeaveUnit::Day, [
                'min_service_months' => 60, 'max_times_per_service' => 1, 'counts_rest_days' => true, 'counts_holidays' => true,
                'requires_attachment' => true, 'notice_days' => 30, 'approval_workflow' => 'leave_request_hr',
            ]],
        ];
        $order = 10;

        foreach ($rows as $code => [$ar, $en, $color, $unit, $rules]) {
            $this->types[$code] = LeaveType::create([
                'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'color' => $color, 'unit' => $unit, 'sort_order' => $order,
                'description' => 'DEMO: Egyptian-style example; check against the labour law and the company rules.',
                'is_paid' => true, 'affects_payroll' => false, 'tracks_balance' => true, 'is_active' => true, 'approval_workflow' => 'leave_request',
                ...$rules,
            ]);
            $order += 10;
        }
    }

    private function policies(): void
    {
        $save = app(SaveLeavePolicy::class);
        $base = ['is_active' => true, 'carry_over_max_days' => 0, 'allow_negative_balance' => false, 'encashable' => false];
        $partTime = EmploymentType::query()->where('code', 'PT')->value('id');
        $field = EmployeeGroup::query()->withoutGlobalScopes()->where('code', 'FIELD')->value('id');
        $policy = fn (string $code, string $ar, string $en, string $type, array $rules) => $save->handle([
            ...$base, 'code' => $code, 'name_ar' => $ar, 'name_en' => $en, 'leave_type_id' => $this->types[$type]->id,
            'scope_type' => LeavePolicyScope::Company, 'scope_id' => null, 'effective_from' => '2020-01-01', ...$rules,
        ]);

        $policy('ANN-STD', 'الاعتيادية حسب سنوات الخدمة', 'Annual leave by service', 'ANNUAL', [
            'accrual_method' => LeaveAccrualMethod::ServiceTiers, 'entitlement_days' => '15',
            'service_tiers' => [['min_years' => 0, 'days' => '15'], ['min_years' => 1, 'days' => '21'], ['min_years' => 10, 'days' => '30']],
            'carry_over_max_days' => '7', 'carry_over_expiry_months' => 3, 'max_balance_days' => '45', 'encashable' => true, 'encashment_max_days' => '10',
        ]);

        if ($partTime) {
            $policy('ANN-PT', 'الاعتيادية للدوام الجزئي', 'Annual leave — part-time', 'ANNUAL', [
                'scope_type' => LeavePolicyScope::EmploymentType, 'scope_id' => $partTime,
                'accrual_method' => LeaveAccrualMethod::Monthly, 'entitlement_days' => '12',
            ]);
        }

        if ($field) {
            $policy('ANN-FIELD', 'الاعتيادية للعاملين الميدانيين', 'Annual leave — field staff', 'ANNUAL', [
                'scope_type' => LeavePolicyScope::EmployeeGroup, 'scope_id' => $field,
                'accrual_method' => LeaveAccrualMethod::ProRataHire, 'entitlement_days' => '24', 'carry_over_max_days' => '10', 'carry_over_expiry_months' => 6,
            ]);
        }

        // Effective-dated: 12 sick days until last year, 15 from 1 January this year (closes the first policy).
        $policy('SICK-OLD', 'المرضية (حتى العام الماضي)', 'Sick leave (until last year)', 'SICK', ['accrual_method' => LeaveAccrualMethod::AnnualUpfront, 'entitlement_days' => '12']);
        $policy('SICK', 'المرضية', 'Sick leave', 'SICK', [
            'accrual_method' => LeaveAccrualMethod::AnnualUpfront, 'entitlement_days' => '15', 'effective_from' => now()->startOfYear()->toDateString(),
        ]);
        $policy('CASUAL', 'العارضة', 'Casual leave', 'CASUAL', ['accrual_method' => LeaveAccrualMethod::AnnualUpfront, 'entitlement_days' => '7']);
        $policy('MATERNITY', 'الوضع', 'Maternity leave', 'MATERNITY', ['accrual_method' => LeaveAccrualMethod::AnnualUpfront, 'entitlement_days' => '90']);
        $policy('HAJJ', 'الحج', 'Hajj leave', 'HAJJ', ['accrual_method' => LeaveAccrualMethod::AnnualUpfront, 'entitlement_days' => '30']);
    }

    /**
     * A few approved days in last leave year, so the rollover has something to carry.
     */
    private function lastYear(CarbonImmutable $yearStart): void
    {
        foreach ($this->staff()->take(8)->values() as $index => $employee) {
            $start = $this->workday($yearStart->addMonths(3 + $index)->addDays(5));
            $this->file($employee, 'ANNUAL', $start, $start->addDays(1 + $index % 3), approve: 2, asAdmin: true);
        }
    }

    private function requests(CarbonImmutable $today): void
    {
        $staff = $this->staff();
        $pick = fn (int $index): Employee => $staff[$index % $staff->count()];
        $accounts = User::query()->whereIn('email', ['employee1@hrmplus.test', 'employee2@hrmplus.test', 'employee3@hrmplus.test'])
            ->with('employee')->get()->keyBy('email');

        // Approved through both levels inside the attendance demo window (attendance shows on_leave).
        foreach ([[0, 30, 2], [1, 26, 1], [2, 22, 3], [3, 19, 0], [4, 15, 2], [5, 12, 1], [6, 8, 2]] as [$who, $daysAgo, $length]) {
            $start = $this->workday($today->subDays($daysAgo));
            $leave = $this->file($pick($who), 'ANNUAL', $start, $start->addDays($length), approve: 2, asAdmin: true, reason: 'Family matters');
            $this->voidPunches($leave);
        }

        // Sick leave with a medical certificate, single HR level, retroactive.
        foreach ([[7, 17, 1], [8, 9, 0], [9, 4, 2]] as [$who, $daysAgo, $length]) {
            $start = $this->workday($today->subDays($daysAgo));
            $leave = $this->file($pick($who), 'SICK', $start, $start->addDays($length), approve: 1, asAdmin: true, attachment: true, reason: 'Flu');
            $this->voidPunches($leave);
        }

        // Casual: a half day and two hours.
        $this->voidPunches($this->file($pick(10), 'CASUAL', $this->workday($today->subDays(6)), null, approve: 2, asAdmin: true, part: LeaveDayPart::FirstHalf));
        $this->file($pick(11), 'CASUAL', $this->workday($today->subDays(3)), null, approve: 2, asAdmin: true, part: LeaveDayPart::Hours, hours: '2');

        // Unpaid leave last month (payroll feed) and earlier this year.
        $this->voidPunches($this->file($pick(12), 'UNPAID', $this->workday($today->subDays(20)), $this->workday($today->subDays(20))->addDay(), approve: 2, asAdmin: true, reason: 'Personal travel'));
        $this->file($pick(13), 'ANNUAL', $this->workday($today->subMonths(5)), $this->workday($today->subMonths(5))->addDays(4), approve: 2, asAdmin: true);
        $this->file($pick(14), 'ANNUAL', $this->workday($today->subMonths(3)), $this->workday($today->subMonths(3))->addDays(2), recordAsApproved: true);

        // Maternity leave in progress (90 calendar days, started 10 days ago).
        $mother = $staff->first(fn (Employee $employee): bool => $employee->gender === Gender::Female && $employee->hire_date?->lt($today->subYear()));

        if ($mother !== null) {
            $start = $today->subDays(10);
            $this->voidPunches($this->file($mother, 'MATERNITY', $start, $start->addDays(89), approve: 1, asAdmin: true, attachment: true));
        }

        // Future approved leave.
        foreach ([[15, 9, 4], [16, 14, 2], [17, 21, 6], [18, 30, 1], [19, 45, 9]] as [$who, $inDays, $length]) {
            $start = $this->workday($today->addDays($inDays));
            $this->file($pick($who), 'ANNUAL', $start, $start->addDays($length), approve: 2);
        }

        // Pending at the first level (managers) and at the HR level.
        foreach ([[20, 10, 2, 0], [21, 12, 1, 0], [22, 16, 3, 0], [23, 7, 0, 1], [24, 25, 4, 1], [25, 33, 2, 1]] as [$who, $inDays, $length, $approved]) {
            $start = $this->workday($today->addDays($inDays));
            $this->file($pick($who), 'ANNUAL', $start, $start->addDays($length), approve: $approved);
        }

        $this->file($pick(26), 'UNPAID', $this->workday($today->addDays(20)), $this->workday($today->addDays(20))->addDays(4), reason: 'Studying for an exam');

        $veteran = $staff->first(fn (Employee $employee): bool => $employee->hire_date?->lt($today->subYears(6)) ?? false);

        if ($veteran !== null) {
            $start = $today->addDays(60);
            $this->file($veteran, 'HAJJ', $start, $start->addDays(20), attachment: true, reason: 'Hajj season');
        }

        // Rejected, withdrawn, cancelled before start, cancellation pending.
        foreach ([[27, 'ANNUAL', 5, 3], [28, 'ANNUAL', 11, 2], [29, 'UNPAID', 13, 1], [30, 'CASUAL', 4, 0]] as [$who, $type, $inDays, $length]) {
            $start = $this->workday($today->addDays($inDays));
            $leave = $this->file($pick($who), $type, $start, $start->addDays($length), part: $type === 'CASUAL' ? LeaveDayPart::SecondHalf : LeaveDayPart::Full);
            $this->reject($leave, 'Peak period — please choose other dates.');
        }

        $withdrawn = $this->file($pick(31), 'ANNUAL', $this->workday($today->addDays(18)), $this->workday($today->addDays(18))->addDay());
        $this->cancel($withdrawn, 'Plans changed');
        $early = $this->file($pick(32), 'ANNUAL', $this->workday($today->addDays(24)), $this->workday($today->addDays(24))->addDays(2), approve: 2);
        $this->cancel($early, 'Trip postponed');

        foreach ([[33, 2, 4], [34, 1, 3]] as [$who, $daysAgo, $length]) {
            $start = $this->workday($today->subDays($daysAgo + 2));
            $leave = $this->file($pick($who), 'ANNUAL', $start, $start->addDays($length), approve: 2, asAdmin: true);
            $this->voidPunches($leave);
            $this->cancel($leave, 'Came back early to cover a colleague');
        }

        // The demo accounts' own portal requests.
        $portal = [
            'employee1@hrmplus.test' => ['ANNUAL', 15, 2, 0],
            'employee2@hrmplus.test' => ['ANNUAL', 28, 3, 1],
            'employee3@hrmplus.test' => ['CASUAL', 8, 0, 0],
        ];

        foreach ($portal as $email => [$type, $inDays, $length, $approve]) {
            $user = $accounts->get($email);

            for ($try = 0; $try < 5 && $user?->employee; $try++) {
                $start = $this->workday($today->addDays($inDays + $try));
                $leave = $this->file($user->employee, $type, $start, $length ? $start->addDays($length) : null, approve: $approve, by: $user,
                    part: $type === 'CASUAL' ? LeaveDayPart::Hours : LeaveDayPart::Full, hours: $type === 'CASUAL' ? '3' : null, reason: 'Requested in the portal');

                if ($leave !== null) {
                    break;
                }
            }
        }

        if ($user = $accounts->get('employee1@hrmplus.test')) {
            $start = $this->workday($today->subDays(13));
            $this->voidPunches($this->file($user->employee, 'ANNUAL', $start, $start, approve: 2, asAdmin: true));
        }
    }

    /**
     * Employed staff without an admin account (nobody decides on their own leave), in a stable order.
     *
     * @return Collection<int, Employee>
     */
    private function staff(): Collection
    {
        $linked = User::query()->admins()->whereNotNull('employee_id')->pluck('employee_id')->all();

        return Employee::query()->withoutGlobalScopes()->employed()->with('groups')
            ->whereNotIn('id', $linked)->whereNotNull('hire_date')
            ->orderBy('id')->get()->values();
    }

    private function file(
        Employee $employee, string $type, CarbonImmutable $start, ?CarbonImmutable $end = null, int $approve = 0, bool $asAdmin = false,
        bool $recordAsApproved = false, LeaveDayPart $part = LeaveDayPart::Full, ?string $hours = null, bool $attachment = false,
        ?User $by = null, ?string $reason = null,
    ): ?LeaveRequest {
        $user = $by ?? ($recordAsApproved ? $this->admins['hr.manager@hrmplus.test'] ?? null : $this->admins['hr.specialist@hrmplus.test'] ?? null);
        $account = $asAdmin || $recordAsApproved ? null : User::query()->where('employee_id', $employee->id)->where('account_type', 'employee')->first();
        $user = $account ?? $user;

        if ($user === null) {
            return null;
        }

        $source = $by !== null || $account !== null ? LeaveRequestSource::Portal : LeaveRequestSource::Admin;
        $files = $attachment ? [$this->certificate()] : [];

        try {
            $leave = app(ManageLeaveRequest::class)->submit($employee, [
                'leave_type_id' => $this->types[$type]->id, 'start_date' => $start->format('Y-m-d'),
                'end_date' => ($end ?? $start)->format('Y-m-d'), 'day_part' => $part->value, 'hours' => $hours, 'reason' => $reason,
            ], $source, $user, $files, $recordAsApproved);
        } catch (ValidationException $exception) {
            $this->skipped++;
            $this->command?->line("  skipped {$type} for {$employee->employee_number}: ".collect($exception->errors())->flatten()->first());

            return null;
        }

        $this->created++;

        for ($level = 0; $level < $approve && $leave->refresh()->isPending(); $level++) {
            $this->decide($leave, true);
        }

        return $leave->refresh();
    }

    private function decide(LeaveRequest $leave, bool $approve, ?string $reason = null): void
    {
        $approval = $leave->latestApprovalRequest()->first();
        $approvals = app(ApprovalService::class);
        $candidates = ['branch.manager@hrmplus.test', 'department.manager@hrmplus.test', 'direct.manager@hrmplus.test', 'hr.manager@hrmplus.test', 'super.admin@hrmplus.test'];

        foreach ($candidates as $email) {
            $user = $this->admins[$email] ?? null;

            if ($user !== null && $approval !== null && $user->employee_id !== $leave->employee_id && $approvals->canAct($approval, $user)) {
                app(ManageLeaveRequest::class)->decide($leave, $user, $approve, $reason);

                return;
            }
        }
    }

    private function reject(?LeaveRequest $leave, string $reason): void
    {
        if ($leave?->isPending()) {
            $this->decide($leave, false, $reason);
        }
    }

    private function cancel(?LeaveRequest $leave, string $reason): void
    {
        if ($leave === null) {
            return;
        }

        $account = User::query()->where('employee_id', $leave->employee_id)->where('account_type', 'employee')->first()
            ?? User::query()->find($leave->requested_by);

        try {
            app(ManageLeaveRequest::class)->cancel($leave, $account, $reason);
        } catch (ValidationException) {
            // The rules refused (e.g. already decided); the request keeps its state.
        }
    }

    /**
     * Void the simulated demo punches of the approved leave days and recalculate them,
     * so attendance shows the leave instead of "worked on leave".
     */
    private function voidPunches(?LeaveRequest $leave): void
    {
        if ($leave === null || ! $leave->isTaken()) {
            return;
        }

        $recorder = app(RawEventRecorder::class);
        $dates = $leave->requestDays()->where('is_counted', true)->where('day_part', LeaveDayPart::Full->value)->get()
            ->map(fn ($day): string => $day->date->format('Y-m-d'))->all();

        if ($dates === []) {
            return;
        }

        AttendanceRawEvent::query()->withoutGlobalScopes()
            ->where('employee_id', $leave->employee_id)->where('kind', RawEventKind::Punch)->whereNull('voided_at')
            ->where('source_reference', 'like', 'DEMO-%')
            ->get()
            ->filter(fn (AttendanceRawEvent $event): bool => in_array($event->occurred_at?->setTimezone('Africa/Cairo')->format('Y-m-d'), $dates, true))
            ->each(fn (AttendanceRawEvent $event) => $recorder->void($event, null, 'Demo: the employee was on approved leave'));
    }

    private function certificate(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'leave').'.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        return new UploadedFile($path, 'medical-certificate-demo.pdf', 'application/pdf', null, true);
    }

    /**
     * The date, or the next Sunday–Thursday.
     */
    private function workday(CarbonImmutable $date): CarbonImmutable
    {
        while (in_array($date->dayOfWeek, [5, 6], true)) {
            $date = $date->addDay();
        }

        return $date;
    }
}
