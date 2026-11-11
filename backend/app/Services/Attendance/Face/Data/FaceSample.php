<?php

namespace App\Services\Attendance\Face\Data;

/**
 * What the mobile app sends for an enrolment or a verification: the
 * provider SDK's session / result token and, for providers that need them,
 * captured images (binary). Liveness fields are passed through from the
 * provider's mobile SDK; a provider may ignore them and decide itself.
 */
final readonly class FaceSample
{
    /**
     * @param  list<string>  $images  binary image contents
     */
    public function __construct(
        public ?string $token = null,
        public array $images = [],
        public ?bool $livenessPassed = null,
        public ?float $livenessScore = null,
    ) {}

    public function isEmpty(): bool
    {
        return ($this->token === null || $this->token === '') && $this->images === [];
    }
}
