<?php

namespace App\Services\Attendance\Face;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Biometric material (face templates, optional enrolment images) on the
 * private biometric disk, encrypted with the application key before it is
 * written (never stored in plain form, never reachable by URL).
 */
class BiometricStore
{
    public function disk(): Filesystem
    {
        return Storage::disk(config('face_verification.disk'));
    }

    public function put(int $employeeId, string $kind, string $contents): string
    {
        $path = $employeeId.'/'.$kind.'-'.Str::uuid().'.enc';
        $this->disk()->put($path, Crypt::encryptString(base64_encode($contents)));

        return $path;
    }

    public function get(?string $path): ?string
    {
        if ($path === null || ! $this->disk()->exists($path)) {
            return null;
        }

        return base64_decode(Crypt::decryptString((string) $this->disk()->get($path)), true) ?: null;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $this->disk()->exists($path)) {
            $this->disk()->delete($path);
        }
    }
}
