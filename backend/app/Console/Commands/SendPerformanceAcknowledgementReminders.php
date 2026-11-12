<?php

namespace App\Console\Commands;

use App\Services\Performance\AcknowledgementReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('performance:send-acknowledgement-reminders')]
#[Description('Remind employees to acknowledge approved performance reviews (every performance.acknowledgement_days days)')]
class SendPerformanceAcknowledgementReminders extends Command
{
    public function handle(AcknowledgementReminders $reminders): int
    {
        $this->components->info('Sent '.$reminders->send().' acknowledgement reminder(s).');

        return self::SUCCESS;
    }
}
