<?php

namespace App\Console\Commands;

use App\Services\Notifications\Automation\AttendanceAlerts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:attendance-alerts')]
#[Description('Notify employees of missing check-outs, late arrivals and absences (and managers of absences), once per day each')]
class SendAttendanceAlerts extends Command
{
    public function handle(AttendanceAlerts $alerts): int
    {
        $counts = $alerts->run();
        $this->info(sprintf('Missing check-out: %d, late: %d, absent: %d.', $counts['missing_checkout'], $counts['late'], $counts['absent']));

        return self::SUCCESS;
    }
}
