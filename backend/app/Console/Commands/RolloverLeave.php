<?php

namespace App\Console\Commands;

use App\Services\Leave\LeaveRollover;
use App\Services\Leave\LeaveYear;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('leave:rollover {--year= : Leave year to open (default: the current leave year)} {--employee= : Only this employee id}')]
#[Description('Close the previous leave year: carry over (within the policy maximum) or expire the remaining balances. Safe to run daily.')]
class RolloverLeave extends Command
{
    public function handle(LeaveRollover $rollover, LeaveYear $years): int
    {
        $year = $this->option('year') ? (int) $this->option('year') : $years->current();
        $count = $rollover->rollover($year, $this->option('employee') ? (int) $this->option('employee') : null);

        $this->info("Rolled {$count} balance(s) from leave year ".($year - 1)." into {$year}.");

        return self::SUCCESS;
    }
}
