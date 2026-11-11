<?php

namespace App\Services\Attendance\Face\Data;

final readonly class FaceEnrolmentResult
{
    public function __construct(
        public ?string $providerReference,
        public ?string $template = null,
        public ?float $qualityScore = null,
    ) {}
}
