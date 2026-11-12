<?php

namespace App\Console\Commands;

use App\Services\Training\CertificateReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('training:send-certificate-reminders')]
#[Description('Remind employees and training managers of certificates expiring within the reminder window (once per certificate)')]
class SendCertificateReminders extends Command
{
    public function handle(CertificateReminders $reminders): int
    {
        $count = $reminders->send();
        $this->info("Reminded {$count} certificate(s).");

        return self::SUCCESS;
    }
}
