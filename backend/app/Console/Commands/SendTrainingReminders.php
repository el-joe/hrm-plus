<?php

namespace App\Console\Commands;

use App\Services\Notifications\Automation\TrainingReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:training-reminders')]
#[Description('Remind enrolled employees of training sessions starting soon (once per enrolment)')]
class SendTrainingReminders extends Command
{
    public function handle(TrainingReminders $reminders): int
    {
        $this->info('Reminded '.$reminders->run().' enrolment(s).');

        return self::SUCCESS;
    }
}
