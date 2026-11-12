<?php

namespace App\Services\Disciplinary;

use App\Models\DisciplinaryCase;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Disciplinary evidence on the private disk "disciplinary_files": random file
 * names inside a per-case folder, never reachable by URL; streamed by the
 * controller after the policy check (and logged).
 */
class DisciplinaryFileStore
{
    public function disk(): Filesystem
    {
        return Storage::disk('disciplinary_files');
    }

    /**
     * @return array{file_path: string, file_name: string, file_mime: string, file_size: int}
     */
    public function store(DisciplinaryCase $case, UploadedFile $file): array
    {
        $extension = Str::lower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $this->disk()->putFileAs((string) $case->getKey(), $file, Str::uuid()->toString().'.'.$extension);

        return [
            'file_path' => $path,
            'file_name' => Str::limit($this->safeName($file->getClientOriginalName()), 250, ''),
            'file_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
        ];
    }

    /**
     * Store raw contents like an upload (demo data).
     *
     * @return array{file_path: string, file_name: string, file_mime: string, file_size: int}
     */
    public function storeContents(DisciplinaryCase $case, string $contents, string $name, string $mime): array
    {
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');
        $path = $case->getKey().'/'.Str::uuid()->toString().'.'.$extension;
        $this->disk()->put($path, $contents);

        return ['file_path' => $path, 'file_name' => $this->safeName($name), 'file_mime' => $mime, 'file_size' => strlen($contents)];
    }

    public function response(string $path, string $name, string $mime): StreamedResponse
    {
        abort_unless($this->disk()->exists($path), 404);

        return $this->disk()->response($path, $name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], 'attachment');
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name) ?? 'file';

        return trim($name) === '' ? 'file' : $name;
    }
}
