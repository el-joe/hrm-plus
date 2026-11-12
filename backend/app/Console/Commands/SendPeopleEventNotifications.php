<?php

namespace App\Console\Commands;

use App\Services\Notifications\Automation\PeopleEvents;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:people-events')]
#[Description('Birthday greetings and notices, work anniversaries and probation-end reminders (once per employee and year)')]
class SendPeopleEventNotifications extends Command
{
    public function handle(PeopleEvents $events): int
    {
        $counts = $events->run();
        $this->info(sprintf('Birthdays: %d, upcoming: %d, anniversaries: %d, probation: %d.', $counts['birthdays'], $counts['upcoming_birthdays'], $counts['anniversaries'], $counts['probation']));

        return self::SUCCESS;
    }
}
