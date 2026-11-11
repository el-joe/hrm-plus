<?php

namespace App\Listeners;

use App\Events\EmployeeTerminated;
use App\Services\Attendance\Face\FaceEnrolmentService;

/**
 * Biometric face data is deleted as soon as a termination takes effect.
 */
class DeleteFaceDataOnTermination
{
    public function __construct(private readonly FaceEnrolmentService $faces) {}

    public function handle(EmployeeTerminated $event): void
    {
        $this->faces->deleteBiometrics($event->employee, 'termination', null);
    }
}
