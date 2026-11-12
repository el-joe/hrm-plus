<?php

namespace Database\Seeders;

use App\Actions\HrRequests\DecideProfileChanges;
use App\Actions\HrRequests\IssueHrLetter;
use App\Actions\HrRequests\ManageHrRequest;
use App\Actions\HrRequests\SubmitHrRequest;
use App\Enums\EmployeeStatus;
use App\Enums\HrRequestSource;
use App\Models\Employee;
use App\Models\HrRequest;
use App\Models\HrRequestCategory;
use App\Models\User;
use App\Services\HrRequests\HrRequestEscalation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Development demo of HR requests, created through the real actions (SLA, assignment, thread, approval
 * engine, letters, profile changes, escalation, notifications), dated as if they happened then:
 *
 * - employee1@ (EMP-00024): a resolved salary certificate with its PDF letter, an applied profile
 *   correction (address + emergency phone), an open salary inquiry WAITING FOR HIS ANSWER, a cancelled request;
 * - employee2@ (EMP-00015): a PENDING profile correction (phone + passport number — apply as hr.manager@), an
 *   employment certificate in progress, a resolved leave inquiry;
 * - employee3@ (EMP-00036, Alexandria): an "Other" request PENDING APPROVAL (approve as branch.manager@ or
 *   hr.manager@) and a document request;
 * - ~20 more requests for other employees in every status, incl. overdue requests escalated to level 1 and 2,
 *   internal notes, a rejection after approval, unassigned ones.
 *
 * Certificates go to hr.specialist@ by default; escalations to hr.manager@. Skipped when requests exist.
 */
class HrRequestDemoSeeder extends Seeder
{
    private SubmitHrRequest $submit;

    private ManageHrRequest $manage;

    private User $hr;

    private User $specialist;

    private bool $issuedExtraLetter = false;

    public function run(): void
    {
        if (HrRequest::query()->withoutGlobalScopes()->exists() || ! Employee::query()->withoutGlobalScopes()->exists()) {
            return;
        }

        config(['queue.default' => 'sync']);
        // Earlier demo seeders fake notifications in this process; Phase 8b demo data goes through the real centre.
        Notification::swap(new ChannelManager(app()));

        $this->submit = app(SubmitHrRequest::class);
        $this->manage = app(ManageHrRequest::class);
        $this->hr = User::query()->where('email', 'hr.manager@hrmplus.test')->firstOrFail();
        $this->specialist = User::query()->where('email', 'hr.specialist@hrmplus.test')->firstOrFail();
        $branchManager = User::query()->where('email', 'branch.manager@hrmplus.test')->firstOrFail();
        $employee1 = User::query()->where('email', 'employee1@hrmplus.test')->firstOrFail();
        $employee2 = User::query()->where('email', 'employee2@hrmplus.test')->firstOrFail();
        $employee3 = User::query()->where('email', 'employee3@hrmplus.test')->firstOrFail();
        $today = CarbonImmutable::now()->setTime(10, 0);

        HrRequestCategory::query()->whereIn('code', ['SALARY_CERT', 'EMPLOYMENT_CERT', 'EXPERIENCE_LETTER'])->update(['default_assignee_id' => $this->specialist->id]);
        HrRequestCategory::query()->update(['escalation_user_id' => $this->hr->id]);

        // employee1@: resolved salary certificate with the issued PDF letter.
        $this->at($today->subDays(20), function () use ($employee1): void {
            $request = $this->file($employee1, 'SALARY_CERT', ['letter_language' => 'ar', 'fields' => ['addressee' => 'البنك الأهلي المصري']], subject: 'Salary certificate for a car loan');
            $this->manage->start($request->refresh(), $this->specialist);
            Carbon::setTestNow(now()->addHours(5));
            app(IssueHrLetter::class)->handle($request->refresh(), $this->hr, 'ar');
        });

        // employee1@: applied profile correction (address + emergency phone).
        $this->at($today->subDays(12), function () use ($employee1): void {
            $request = $this->file($employee1, 'PROFILE_CORRECTION', ['changes' => ['address' => '14 Abbas El Akkad St., Nasr City', 'emergency_phone' => '+20 100 555 7788']]);
            Carbon::setTestNow(now()->addHours(20));
            app(DecideProfileChanges::class)->apply($request->refresh(), $this->hr, 'Updated — thank you.');
        });

        // employee1@: salary inquiry waiting for his answer.
        $this->at($today->subDays(2), function () use ($employee1): void {
            $request = $this->file($employee1, 'SALARY_INQUIRY', ['description' => 'My October payslip shows a deduction "PENALTY" I do not recognise. Can you explain it?', 'fields' => ['period' => 'October 2026']]);
            $this->manage->assign($request->refresh(), $this->specialist, $this->hr);
            $this->manage->reply($request->refresh(), $this->specialist, 'Linked to disciplinary case DIS — confirm with HR manager before replying.', internal: true);
            Carbon::setTestNow(now()->addHours(3));
            $this->manage->reply($request->refresh(), $this->specialist, 'Thank you. Could you tell us which line exactly (the amount) so we can check it with payroll?', awaitEmployee: true);
        });

        // employee1@: cancelled by himself.
        $this->at($today->subDays(30), function () use ($employee1): void {
            $request = $this->file($employee1, 'GENERAL_INQUIRY', ['description' => 'Is there a gym membership benefit for employees?']);
            $this->manage->cancel($request->refresh(), $employee1, 'Found the answer in the handbook.');
        });

        // employee2@: pending profile correction (phone + passport number) — apply as hr.manager@.
        $this->at($today->subDays(1), fn () => $this->file($employee2, 'PROFILE_CORRECTION', ['changes' => [
            'phone' => '+20 122 345 6789', 'passport_number' => 'A31234567',
        ]], source: HrRequestSource::Api));

        // employee2@: employment certificate in progress, English.
        $this->at($today->subDays(1)->subHours(4), function () use ($employee2): void {
            $request = $this->file($employee2, 'EMPLOYMENT_CERT', ['letter_language' => 'en', 'fields' => ['addressee' => 'Embassy of Germany, Cairo']], subject: 'Employment certificate for a visa');
            $this->manage->start($request->refresh(), $this->specialist);
        });

        // employee2@: resolved leave inquiry.
        $this->at($today->subDays(8), function () use ($employee2): void {
            $request = $this->file($employee2, 'LEAVE_INQUIRY', ['description' => 'How many of my annual leave days carry over to next year?']);
            $this->manage->assign($request->refresh(), $this->specialist, $this->hr);
            Carbon::setTestNow(now()->addHours(6));
            $this->manage->resolve($request->refresh(), $this->specialist, 'Up to 7 days carry over and expire at the end of March. Your current balance is shown under My leaves.');
        });

        // employee3@: "Other" pending approval (branch.manager@ / hr.manager@), and a document request.
        $this->at($today->subHours(20), fn () => $this->file($employee3, 'OTHER', ['description' => 'I would like to move to the morning shift permanently because of my evening studies.'], subject: 'Permanent morning shift'));
        $this->at($today->subDays(3), function () use ($employee3): void {
            $request = $this->file($employee3, 'DOCUMENT_REQUEST', ['description' => 'Please send me a copy of my employment contract.', 'fields' => ['document_type' => 'Employment contract copy']], attach: true);
            $this->manage->assign($request->refresh(), $this->specialist, $this->hr);
        });

        $this->others($today, $branchManager);

        // Overdue requests escalate: once two days ago (still overdue today → level 2 for the oldest), then now.
        Carbon::setTestNow($today->subHours(30));
        app(HrRequestEscalation::class)->run(CarbonImmutable::now());
        Carbon::setTestNow();
        app(HrRequestEscalation::class)->run(CarbonImmutable::now());
    }

    /**
     * About twenty requests of other employees in every status.
     */
    private function others(CarbonImmutable $today, User $branchManager): void
    {
        $employees = $this->otherEmployees();
        $topics = [
            ['SALARY_CERT', 'Salary certificate for a rent contract', ['letter_language' => 'ar', 'fields' => ['addressee' => 'To whom it may concern']]],
            ['EMPLOYMENT_CERT', 'Employment certificate for my bank', ['letter_language' => 'en']],
            ['LEAVE_INQUIRY', 'Sick leave balance question', ['description' => 'How many paid sick days do I have left this year?']],
            ['SALARY_INQUIRY', 'Overtime not paid', ['description' => 'My 6 overtime hours from 14 October are not on the payslip.', 'fields' => ['period' => 'October 2026']]],
            ['GENERAL_INQUIRY', 'Medical insurance card', ['description' => 'My medical insurance card has not arrived yet.']],
            ['EXPERIENCE_LETTER', 'Experience letter', ['letter_language' => 'en']],
            ['GENERAL_INQUIRY', 'Training budget for a certification', ['description' => 'Can the company sponsor the PMP exam fee?']],
            ['OTHER', 'Remote work two days a week', ['description' => 'I would like to work from home on Sundays and Thursdays.']],
        ];
        $plans = ['resolved', 'in_progress', 'open', 'overdue', 'resolved', 'rejected', 'open_unassigned', 'approval_rejected', 'overdue_old', 'resolved', 'awaiting',
            'in_progress', 'resolved', 'open', 'overdue', 'cancelled_by_hr', 'resolved', 'approved_open', 'in_progress', 'resolved'];

        foreach ($plans as $index => $plan) {
            $employee = $employees[$index % max(1, $employees->count())] ?? null;

            if ($employee === null) {
                continue;
            }

            // Their own account when they have one (portal), otherwise HR files it on their behalf.
            $user = User::query()->where('employee_id', $employee->id)->where('account_type', 'employee')->first();

            [$code, $subject, $data] = $topics[$index % count($topics)];

            if (in_array($plan, ['approval_rejected', 'approved_open'], true)) {
                $code = 'OTHER';
                $data = ['description' => 'Request to change my weekly rest day to Saturday.'];
                $subject = 'Change of rest day';
            } elseif ($code === 'OTHER') {
                $code = 'GENERAL_INQUIRY';
            }

            $submittedAt = match ($plan) {
                'overdue' => $today->subDays(4),
                'overdue_old' => $today->subDays(9),
                'open', 'open_unassigned', 'awaiting' => $today->subHours(6 + $index),
                default => $today->subDays(5 + $index),
            };

            $this->at($submittedAt, function () use ($plan, $user, $employee, $code, $subject, $data, $branchManager): void {
                $request = $this->file($user ?? $this->specialist, $code, $data, subject: $subject, source: $user ? HrRequestSource::Portal : HrRequestSource::Admin, employee: $employee);
                $category = $request->category;

                match ($plan) {
                    'resolved' => $this->resolveLater($request, $category->isLetter()),
                    'in_progress', 'overdue', 'overdue_old' => $this->manage->start($request->refresh(), $this->specialist),
                    'open' => $this->manage->assign($request->refresh(), $this->hr, $this->hr),
                    'awaiting' => $this->manage->reply($request->refresh(), $this->specialist, 'Please attach a scan of the document so we can proceed.', awaitEmployee: true),
                    'rejected' => $this->manage->reject($request->refresh(), $this->hr, 'This is handled by the line manager, not HR. Please contact your manager.'),
                    'cancelled_by_hr' => $this->manage->cancel($request->refresh(), $this->hr, 'Duplicate of an earlier request.'),
                    'approval_rejected' => $this->manage->decideApproval($request->refresh(), $branchManager->can('hr_requests.approve') && $request->isVisibleTo($branchManager) ? $branchManager : $this->hr, false, 'Rest days are fixed by the branch schedule.'),
                    'approved_open' => $this->manage->decideApproval($request->refresh(), $this->hr, true, 'Agreed for next month.'),
                    default => null,
                };

                if (in_array($plan, ['in_progress', 'overdue', 'overdue_old'], true)) {
                    $this->manage->reply($request->refresh(), $this->specialist, 'Checked with payroll; waiting for their confirmation.', internal: true);
                }
            });
        }
    }

    private function resolveLater(HrRequest $request, bool $letter): void
    {
        $this->manage->start($request->refresh(), $this->specialist);
        Carbon::setTestNow(now()->addHours(8));

        // One more generated PDF letter (keeps the seed fast); the other certificates are resolved with a note.
        if ($letter && ! $this->issuedExtraLetter) {
            $this->issuedExtraLetter = true;
            app(IssueHrLetter::class)->handle($request->refresh(), $this->hr);

            return;
        }

        $this->manage->reply($request->refresh(), $this->specialist, 'Done — you can collect the original from the HR office.');
        $this->manage->resolve($request->refresh(), $this->specialist, null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function file(User $user, string $code, array $data, ?string $subject = null, HrRequestSource $source = HrRequestSource::Portal, bool $attach = false, ?Employee $employee = null): HrRequest
    {
        $employee ??= Employee::query()->withoutGlobalScopes()->findOrFail($user->employee_id);
        $category = HrRequestCategory::query()->where('code', $code)->with('fields')->firstOrFail();
        $files = [];

        if ($attach || $category->requires_attachment) {
            $path = tempnam(sys_get_temp_dir(), 'hrq');
            file_put_contents($path, "%PDF-1.4\n% demo attachment\n");
            $files[] = new UploadedFile($path, 'scan.pdf', 'application/pdf', null, true);
        }

        return $this->submit->handle($employee, $category, ['subject' => $subject, ...$data], $user, $source, $files);
    }

    /**
     * @return Collection<int, Employee>
     */
    private function otherEmployees(): Collection
    {
        $excluded = User::query()->whereIn('email', ['employee1@hrmplus.test', 'employee2@hrmplus.test', 'employee3@hrmplus.test', 'hr.manager@hrmplus.test', 'hr.specialist@hrmplus.test'])
            ->whereNotNull('employee_id')->pluck('employee_id');

        return Employee::query()->withoutGlobalScopes()->where('status', EmployeeStatus::Active)->whereNotIn('id', $excluded)
            ->orderBy('id')->limit(12)->get()->values();
    }

    private function at(CarbonImmutable $moment, callable $callback): void
    {
        // Never in the future (the demo may be seeded early in the morning).
        $now = CarbonImmutable::now();
        Carbon::setTestNow($moment->greaterThan($now) ? $now->subMinutes(30) : $moment);

        try {
            $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
