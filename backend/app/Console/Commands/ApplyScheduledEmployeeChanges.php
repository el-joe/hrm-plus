<?php

namespace App\Console\Commands;

use App\Services\Employees\EmploymentHistory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('employees:apply-scheduled-changes')]
#[Description('Apply future-dated transfers, promotions, terminations and salary changes whose date has arrived')]
class ApplyScheduledEmployeeChanges extends Command
{
    public function handle(EmploymentHistory $history): int
    {
        $counts = $history->applyDue();

        $this->components->info("Applied {$counts['assignments']} placement and {$counts['salaries']} salary change(s).");

        return self::SUCCESS;
    }
}
