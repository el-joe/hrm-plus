<?php

namespace App\Services\Recruitment;

use App\Models\Candidate;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Candidate CVs and attachments on the private disk (config('recruitment.disk')),
 * under random names in a per-candidate folder. Never reachable by URL:
 * controllers stream them after their own authorisation check.
 */
class RecruitmentFileStore
{
    public function disk(): Filesystem
    {
        return Storage::disk(config('recruitment.disk'));
    }

    /**
     * @return array{path: string, name: string, mime: string, size: int}
     */
    public function store(Candidate $candidate, UploadedFile $file, string $folder): array
    {
        $extension = Str::lower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $this->disk()->putFileAs($candidate->getKey().'/'.$folder, $file, Str::uuid()->toString().'.'.$extension);
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $file->getClientOriginalName()) ?: 'file';

        return [
            'path' => $path,
            'name' => Str::limit(trim($name) === '' ? 'file' : $name, 250, ''),
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => (int) $file->getSize(),
        ];
    }

    /**
     * Store a CV on the candidate (replacing the previous file).
     */
    public function storeCv(Candidate $candidate, UploadedFile $file): void
    {
        $previous = $candidate->cv_path;
        $stored = $this->store($candidate, $file, 'cv');

        $candidate->forceFill([
            'cv_path' => $stored['path'],
            'cv_name' => $stored['name'],
            'cv_mime' => $stored['mime'],
            'cv_size' => $stored['size'],
        ])->save();

        if ($previous !== null && $previous !== $stored['path']) {
            $this->delete($previous);
        }
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $this->disk()->exists($path);
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            $this->disk()->delete($path);
        }
    }

    public function response(string $path, string $name, string $mime): StreamedResponse
    {
        abort_unless($this->exists($path), 404);

        return $this->disk()->response($path, $name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'attachment');
    }
}
