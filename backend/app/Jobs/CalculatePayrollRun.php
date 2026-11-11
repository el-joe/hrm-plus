<?php

namespace App\Jobs;

use App\Actions\Payroll\ManagePayrollRun;
use App\Enums\PayrollRunStatus;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Background calculation of a large payroll run (above setting
 * payroll.sync_calculation_limit employees). The run was already claimed
 * ("calculating") by ManagePayrollRun::calculate(); the run page shows
 * progress_done / progress_total. Unique per run, so it never overlaps itself.
 */
class CalculatePayrollRun implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $runId, public ?int $userId, public string $previousStatus) {}

    public function uniqueId(): string
    {
        return (string) $this->runId;
    }

    public function handle(ManagePayrollRun $runs): void
    {
        $run = PayrollRun::query()->withoutGlobalScopes()->find($this->runId);

        if ($run === null || $run->status !== PayrollRunStatus::Calculating) {
            return;
        }

        $runs->runCalculation($run, $this->userId ? User::query()->find($this->userId) : null, PayrollRunStatus::from($this->previousStatus));
    }
}
