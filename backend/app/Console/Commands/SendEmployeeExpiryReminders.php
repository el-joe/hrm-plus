<?php

namespace App\Console\Commands;

use App\Services\Employees\ExpiryReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('employees:send-expiry-reminders')]
#[Description('Notify about employee documents and contracts expiring within the configured lead time')]
class SendEmployeeExpiryReminders extends Command
{
    public function handle(ExpiryReminders $reminders): int
    {
        $counts = $reminders->send();

        $this->components->info("Reminded about {$counts['documents']} document(s) and {$counts['contracts']} contract(s).");

        return self::SUCCESS;
    }
}
