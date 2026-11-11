<?php

namespace App\Enums;

/**
 * Result of one face verification call.
 */
enum FaceVerificationResult: string
{
    case Matched = 'matched';
    case NotMatched = 'not_matched';
    case LivenessFailed = 'liveness_failed';
    case NotEnrolled = 'not_enrolled';
    case NotProvided = 'not_provided';
    case ProviderError = 'provider_error';
    case Skipped = 'skipped';

    public function label(): string
    {
        return __('face.enums.results.'.$this->value);
    }

    public function isSuccess(): bool
    {
        return $this === self::Matched;
    }

    /**
     * A failed verification counts against the retry limit.
     */
    public function isFailure(): bool
    {
        return in_array($this, [self::NotMatched, self::LivenessFailed, self::ProviderError], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Matched => 'success',
            self::Skipped, self::NotProvided => 'neutral',
            self::NotEnrolled, self::ProviderError => 'warning',
            default => 'danger',
        };
    }
}
