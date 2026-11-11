<?php

namespace App\Services\Attendance\Face;

use RuntimeException;

/**
 * The face provider could not process the request (unreachable, rejected
 * sample, ...). The code is a short machine-readable reason.
 */
class FaceProviderException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $reason);
    }
}
