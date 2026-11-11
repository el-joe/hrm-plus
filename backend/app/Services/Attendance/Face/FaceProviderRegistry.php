<?php

namespace App\Services\Attendance\Face;

use App\Services\Attendance\Face\Contracts\FaceVerificationProvider;
use InvalidArgumentException;

/**
 * The configured face provider (Settings → Face verification → provider).
 */
class FaceProviderRegistry
{
    public function key(): string
    {
        return (string) setting('face_verification.provider');
    }

    public function current(): FaceVerificationProvider
    {
        return $this->make($this->key());
    }

    public function make(string $key): FaceVerificationProvider
    {
        $class = config('face_verification.providers.'.$key.'.class');

        if (! is_string($class) || ! is_a($class, FaceVerificationProvider::class, true)) {
            throw new InvalidArgumentException("Unknown face verification provider [{$key}].");
        }

        return app($class);
    }

    public function isSimulated(?string $key = null): bool
    {
        return (bool) config('face_verification.providers.'.($key ?? $this->key()).'.simulated', false);
    }
}
