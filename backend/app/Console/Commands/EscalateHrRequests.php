<?php

namespace App\Console\Commands;

use App\Services\HrRequests\HrRequestEscalation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('hr-requests:escalate')]
#[Description('Escalate HR requests past their SLA due time (one level per run window, up to the configured maximum)')]
class EscalateHrRequests extends Command
{
    public function handle(HrRequestEscalation $escalation): int
    {
        $count = $escalation->run();
        $this->info("Escalated {$count} request(s).");

        return self::SUCCESS;
    }
}
