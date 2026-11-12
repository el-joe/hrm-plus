<?php

namespace App\Console\Commands;

use App\Services\Notifications\Automation\ApprovalsDigest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:approvals-digest')]
#[Description('Send approvers a daily digest of approval requests waiting longer than the reminder window')]
class SendApprovalsDigest extends Command
{
    public function handle(ApprovalsDigest $digest): int
    {
        $this->info('Digests sent: '.$digest->run().'.');

        return self::SUCCESS;
    }
}
