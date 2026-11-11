<?php

namespace App\Services\Attendance\Face\Data;

final readonly class FaceMatchResult
{
    public function __construct(
        public bool $matched,
        public ?float $score = null,
        public ?bool $livenessPassed = null,
        public ?float $livenessScore = null,
        public ?string $providerReference = null,
    ) {}
}
