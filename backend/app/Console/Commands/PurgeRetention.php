<?php

namespace App\Console\Commands;

use App\Services\Notifications\RetentionPurge;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('system:purge-retention')]
#[Description('Delete read notifications, delivery log rows, login history and idle device tokens past their retention period')]
class PurgeRetention extends Command
{
    public function handle(RetentionPurge $purge): int
    {
        foreach ($purge->run() as $what => $count) {
            $this->components->twoColumnDetail($what, (string) $count);
        }

        return self::SUCCESS;
    }
}
