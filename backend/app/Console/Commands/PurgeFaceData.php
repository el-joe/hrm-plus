<?php

namespace App\Console\Commands;

use App\Services\Attendance\Face\FaceEnrolmentService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('face:purge')]
#[Description('Delete face data past its retention period (old enrolments, terminated employees, old verification results)')]
class PurgeFaceData extends Command
{
    public function handle(FaceEnrolmentService $faces): int
    {
        $result = $faces->purge();
        $this->info("Deleted {$result['enrolments']} enrolment(s) and {$result['verifications']} verification record(s).");

        return self::SUCCESS;
    }
}
