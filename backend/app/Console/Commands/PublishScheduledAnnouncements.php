<?php

namespace App\Console\Commands;

use App\Actions\Announcements\ManageAnnouncement;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('announcements:publish-scheduled')]
#[Description('Notify the audience of published announcements whose publish time has arrived (once per announcement)')]
class PublishScheduledAnnouncements extends Command
{
    public function handle(ManageAnnouncement $announcements): int
    {
        $count = $announcements->sendDue();
        $this->info("Announcements notified: {$count}.");

        return self::SUCCESS;
    }
}
