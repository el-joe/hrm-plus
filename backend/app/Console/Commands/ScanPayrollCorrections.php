<?php

namespace App\Console\Commands;

use App\Services\Payroll\PayrollCorrections;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payroll:scan-corrections {--months=6 : How many months of finalised periods to compare}')]
#[Description('Turn attendance / leave changes made after a payroll period was finalised into pending payroll adjustments')]
class ScanPayrollCorrections extends Command
{
    public function handle(PayrollCorrections $corrections): int
    {
        $created = $corrections->scan(months: max(1, (int) $this->option('months')));
        $this->components->info("Payroll adjustments created: {$created}.");

        return self::SUCCESS;
    }
}
