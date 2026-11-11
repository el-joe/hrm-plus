<?php

namespace App\Console\Commands;

use App\Services\Recruitment\InterviewReminders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('recruitment:send-interview-reminders')]
#[Description('Remind interviewers and candidates of interviews starting within the configured lead time')]
class SendInterviewReminders extends Command
{
    public function handle(InterviewReminders $reminders): int
    {
        $this->components->info('Reminded about '.$reminders->send().' interview(s).');

        return self::SUCCESS;
    }
}
