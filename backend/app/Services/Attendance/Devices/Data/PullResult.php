<?php

namespace App\Services\Attendance\Devices\Data;

/**
 * What an adapter read from a device in one pull.
 */
final readonly class PullResult
{
    /**
     * @param  list<DevicePunch>  $punches
     * @param  ?string  $cursor  where the next pull continues (null: keep the current cursor)
     */
    public function __construct(
        public array $punches,
        public ?string $cursor,
        public ?string $message = null,
    ) {}
}
