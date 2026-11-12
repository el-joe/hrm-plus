<?php

namespace App\Services\Notifications\Push;

/**
 * Outcome of sending one push message to a user's devices.
 */
final readonly class PushResult
{
    /**
     * @param  list<int>  $invalidDeviceIds  devices whose token the provider rejected as unregistered (to delete)
     * @param  list<string>  $messageIds
     */
    public function __construct(
        public int $sent,
        public int $failed,
        public array $invalidDeviceIds = [],
        public array $messageIds = [],
        public ?string $error = null,
        public bool $simulated = false,
        public bool $retryable = false,
    ) {}
}
