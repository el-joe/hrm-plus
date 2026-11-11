<?php

namespace App\Services\Attendance\Data;

use App\Enums\PunchDirection;
use App\Enums\RawEventSource;
use Carbon\CarbonImmutable;

/**
 * A punch as the calculator sees it (decoupled from Eloquent so the
 * calculation is a pure function of its inputs).
 */
final readonly class PunchInput
{
    public function __construct(
        public int $id,
        public int $rawEventId,
        public CarbonImmutable $at,
        public PunchDirection $direction,
        public RawEventSource $source,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'punch_id' => $this->id,
            'raw_event_id' => $this->rawEventId,
            'at' => $this->at->toIso8601ZuluString(),
            'direction' => $this->direction->value,
            'source' => $this->source->value,
        ];
    }
}
