<?php

namespace App\Console\Commands;

use App\Services\Leave\LeaveAccrualService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leave:accrue {--date= : Accrue as of this date (Y-m-d), default today} {--employee= : Only this employee id}')]
#[Description('Credit leave entitlements due by the date (idempotent) and expire unused carried-over days past their expiry')]
class AccrueLeave extends Command
{
    public function handle(LeaveAccrualService $accruals): int
    {
        $date = CarbonImmutable::parse($this->option('date') ?: now()->toDateString())->startOfDay();
        $employee = $this->option('employee') ? (int) $this->option('employee') : null;

        $credited = $accruals->accrueAll($date, $employee);
        $expired = $accruals->expireCarryOver($date);

        $this->info("Accrual rows: {$credited}; carry-over expiries: {$expired} (as of {$date->toDateString()}).");

        return self::SUCCESS;
    }
}
