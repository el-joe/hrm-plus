<?php

namespace App\Services\Attendance\Devices\Data;

/**
 * Result of an adapter's connection test.
 *
 * @param  array<string, scalar|null>  $details  e.g. firmware, user count, log count
 */
final readonly class ConnectionResult
{
    /**
     * @param  array<string, scalar|null>  $details
     */
    public function __construct(
        public bool $ok,
        public string $message,
        public array $details = [],
    ) {}
}
