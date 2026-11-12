<?php

namespace Database\Seeders;

use App\Actions\Disciplinary\ManageDisciplinaryAction;
use App\Actions\Disciplinary\ManageDisciplinaryCase;
use App\Enums\DisciplinaryNoteType;
use App\Enums\EmployeeStatus;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Models\Employee;
use App\Models\User;
use App\Models\ViolationType;
use App\Services\Disciplinary\DisciplinaryFileStore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Development demo of disciplinary cases, created through the real actions
 * (approval engine, escalation, payroll deduction, audit), dated as if they happened then:
 *
 * - closed: a verbal warning to employee1@ (EMP-00024) in July, with his response;
 * - issued: a WRITTEN warning to employee1@ two weeks ago — escalated from the active
 *   verbal warning — awaiting his response in the portal;
 * - issued: a salary deduction (misuse of company assets) — its pending deduction pay
 *   entry waits in the payroll_deduction workflow (approve as finance.officer@);
 * - pending approval: a suspension proposed by super.admin@ (approve as hr.manager@);
 * - investigating: notes, an interview record and a demo evidence file;
 * - reported: by branch.manager@ about employee3@ (Alexandria) — branch.manager@ sees only
 *   its status (no disciplinary.view);
 * - dismissed: no action.
 *
 * Proposals are made by super.admin@ and decided by hr.manager@ (the engine never lets the
 * proposer approve). Skipped when cases exist.
 */
class DisciplinaryDemoSeeder extends Seeder
{
    private User $hr;

    private User $super;

    public function run(): void
    {
        if (DisciplinaryCase::query()->withoutGlobalScopes()->exists() || ! Employee::query()->withoutGlobalScopes()->exists()) {
            return;
        }

        config(['queue.default' => 'sync']);
        Notification::fake();

        $this->hr = User::query()->where('email', 'hr.manager@hrmplus.test')->firstOrFail();
        $this->super = User::query()->where('email', 'super.admin@hrmplus.test')->firstOrFail();
        $directManager = User::query()->where('email', 'direct.manager@hrmplus.test')->firstOrFail();
        $branchManager = User::query()->where('email', 'branch.manager@hrmplus.test')->firstOrFail();
        $specialist = User::query()->where('email', 'hr.specialist@hrmplus.test')->firstOrFail();
        $employee1 = User::query()->where('email', 'employee1@hrmplus.test')->firstOrFail();
        $employee3 = User::query()->where('email', 'employee3@hrmplus.test')->firstOrFail();
        $others = $this->otherEmployees([$employee1->employee_id, $employee3->employee_id, $this->hr->employee_id, $directManager->employee_id, $branchManager->employee_id]);
        $today = CarbonImmutable::today();

        $cases = app(ManageDisciplinaryCase::class);
        $actions = app(ManageDisciplinaryAction::class);

        // 1. Closed: verbal warning in July, with the employee's response.
        $this->at($today->subMonthsNoOverflow(4)->setDay(8), function () use ($cases, $actions, $directManager, $employee1): void {
            $case = $cases->report([
                'employee_id' => $employee1->employee_id, 'violation_type_id' => $this->type('LATE')->id, 'incident_date' => now()->subDays(2)->toDateString(),
                'location' => 'Cairo HQ', 'description' => 'Arrived more than 30 minutes late on five working days this month without notifying his manager.',
            ], $directManager);
            $cases->startInvestigation($case, $this->super);
            $cases->addNote($case, DisciplinaryNoteType::Interview, 'Met the employee: traffic and a family matter; agreed to inform the manager in advance.', $this->super);
            $action = $actions->propose($case, [
                'type' => 'verbal_warning', 'effective_date' => now()->toDateString(),
                'details' => 'Verbal warning for repeated late arrival. Please respect the working hours and notify your manager when delayed.',
            ], $this->super);
            $actions->decide($action, $this->hr, true, 'Agreed.');
            $actions->respond($action->refresh(), $employee1, 'I apologise. I have changed my commute and will notify my manager in advance from now on.');
            $cases->close($case->refresh(), $this->super, 'Employee responded; no further action.');
        });

        // 2. Issued: written warning (escalated from the active verbal warning), awaiting the response.
        $this->at($today->subDays(14), function () use ($cases, $actions, $directManager, $employee1): void {
            $case = $cases->report([
                'employee_id' => $employee1->employee_id, 'violation_type_id' => $this->type('POLICY')->id, 'incident_date' => now()->subDay()->toDateString(),
                'location' => 'Client visit, Nasr City', 'description' => 'Shared a client price list with a competitor contact by e-mail, against the confidentiality policy.',
            ], $directManager);
            $cases->startInvestigation($case, $this->super);
            $cases->addNote($case, DisciplinaryNoteType::Finding, 'E-mail confirmed by IT. The employee has an active verbal warning (July).', $this->super);
            $cases->recordFindings($case, 'Policy breach confirmed; second offence within the warning validity.', $this->super);
            $action = $actions->propose($case, [
                'type' => 'written_warning', 'effective_date' => now()->toDateString(),
                'details' => 'Written warning for breaching the confidentiality policy. A further breach may lead to a final warning.',
            ], $this->super);
            $actions->decide($action, $this->hr, true);
        });

        // 3. Issued: salary deduction for misuse of company assets (pending deduction pay entry).
        if ($employee = $others->shift()) {
            $this->at($today->subDays(6), function () use ($cases, $actions, $specialist, $employee): void {
                $case = $cases->report([
                    'employee_id' => $employee->id, 'violation_type_id' => $this->type('ASSETS')->id, 'incident_date' => now()->subDays(3)->toDateString(),
                    'location' => 'Warehouse', 'description' => 'Used the company vehicle for personal trips over the weekend; fuel card charged 1,250 EGP.',
                ], $specialist);
                $cases->startInvestigation($case, $this->super);
                $cases->addNote($case, DisciplinaryNoteType::Note, 'Fuel card statement and GPS log reviewed.', $this->super);
                $action = $actions->propose($case, [
                    'type' => 'salary_deduction', 'effective_date' => now()->toDateString(), 'deduction_amount' => '1250.00',
                    'details' => 'Deduction of the fuel cost of personal trips with the company vehicle.',
                ], $this->super);
                $actions->decide($action, $this->hr, true);
            });
        }

        // 4. Pending approval: a suspension proposed by super.admin@.
        if ($employee = $others->shift()) {
            $this->at($today->subDays(3), function () use ($cases, $actions, $employee): void {
                $case = $cases->report([
                    'employee_id' => $employee->id, 'violation_type_id' => $this->type('SAFETY')->id, 'incident_date' => now()->subDay()->toDateString(),
                    'location' => 'Warehouse loading bay', 'description' => 'Operated the forklift without a safety helmet and ignored the supervisor\'s instruction to stop.',
                ], $this->super);
                $cases->startInvestigation($case, $this->super);
                $cases->addNote($case, DisciplinaryNoteType::Interview, 'Two witnesses confirm; the employee admits the facts.', $this->super);
                $actions->propose($case, [
                    'type' => 'suspension', 'effective_date' => now()->addDays(3)->toDateString(),
                    'suspension_start' => now()->addDays(3)->toDateString(), 'suspension_end' => now()->addDays(5)->toDateString(), 'suspension_paid' => false,
                    'details' => 'Three-day unpaid suspension for a serious safety violation.',
                ], $this->super);
            });
        }

        // 5. Investigating, with notes and a demo evidence file.
        if ($employee = $others->shift()) {
            $this->at($today->subDays(2), function () use ($cases, $specialist, $employee): void {
                $case = $cases->report([
                    'employee_id' => $employee->id, 'violation_type_id' => $this->type('CONDUCT')->id, 'incident_date' => now()->subDay()->toDateString(),
                    'location' => 'Cairo HQ, 3rd floor', 'description' => 'Verbal altercation with a colleague in the open office; raised voice and offensive language reported by two colleagues.',
                ], $specialist);
                $cases->startInvestigation($case, $this->hr);
                $cases->addNote($case, DisciplinaryNoteType::Note, 'Statements requested from the two witnesses.', $this->hr);
                $cases->addNote($case, DisciplinaryNoteType::Interview, 'Employee interviewed: says he was provoked; asks that the colleague is heard as well.', $this->hr);
                $evidence = app(DisciplinaryFileStore::class)->storeContents($case,
                    "DEMO — witness statement\n\nOn the reported day at about 11:00 the two colleagues argued loudly near the meeting room.\n",
                    'witness-statement-demo.txt', 'text/plain');
                $case->evidence()->create([...$evidence, 'description' => 'Witness statement (demo)', 'uploaded_by' => $this->hr->id]);
            });
        }

        // 6. Reported by the branch manager (sees only the status of what they reported).
        $this->at($today->subDay(), function () use ($cases, $branchManager, $employee3): void {
            $cases->report([
                'employee_id' => $employee3->employee_id, 'violation_type_id' => $this->type('ABSENT')->id, 'incident_date' => now()->subDay()->toDateString(),
                'location' => 'Alexandria warehouse', 'description' => 'Did not come to work and could not be reached by phone during the day.',
            ], $branchManager);
        });

        // 7. Dismissed: no action.
        if ($employee = $others->shift()) {
            $this->at($today->subDays(20), function () use ($cases, $directManager, $employee): void {
                $case = $cases->report([
                    'employee_id' => $employee->id, 'violation_type_id' => $this->type('LATE')->id, 'incident_date' => now()->subDay()->toDateString(),
                    'description' => 'Late for the weekly meeting twice.',
                ], $directManager);
                $cases->startInvestigation($case, $this->super);
                $cases->dismiss($case, $this->super, 'Both delays were approved in advance by the department head; no violation.');
            });
        }

        $this->command?->info('Disciplinary demo: '.DisciplinaryCase::query()->withoutGlobalScopes()->count().' cases, '
            .DisciplinaryAction::query()->withoutGlobalScopes()->count().' actions.');
    }

    private function type(string $code): ViolationType
    {
        return ViolationType::query()->where('code', $code)->firstOrFail();
    }

    /**
     * Active employees without a demo login, Cairo first.
     *
     * @param  list<int|null>  $except
     * @return Collection<int, Employee>
     */
    private function otherEmployees(array $except): Collection
    {
        $linked = User::query()->whereNotNull('employee_id')->pluck('employee_id')->all();

        return Employee::query()->withoutGlobalScopes()
            ->where('status', EmployeeStatus::Active)
            ->whereNotIn('id', array_filter([...$except, ...$linked]))
            ->whereNotNull('manager_id')
            ->orderBy('employee_number')->skip(10)->take(4)->get()->values();
    }

    private function at(CarbonImmutable $moment, callable $callback): void
    {
        Carbon::setTestNow($moment->setTime(10, 0));

        try {
            $callback();
        } finally {
            Carbon::setTestNow();
        }
    }
}
