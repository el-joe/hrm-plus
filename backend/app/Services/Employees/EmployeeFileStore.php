<?php

namespace App\Services\Employees;

use App\Models\Employee;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employee files on the private disk (config('employees.disk')). Stored
 * under random names inside a per-employee folder, never under public/ and
 * never reachable by URL: callers stream them after their own
 * authorisation check.
 */
class EmployeeFileStore
{
    public function disk(): Filesystem
    {
        return Storage::disk(config('employees.disk'));
    }

    /**
     * @return array{file_path: string, file_name: string, file_mime: string, file_size: int}
     */
    public function store(Employee $employee, UploadedFile $file, string $folder): array
    {
        $extension = Str::lower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $this->disk()->putFileAs($employee->getKey().'/'.$folder, $file, Str::uuid()->toString().'.'.$extension);

        return [
            'file_path' => $path,
            'file_name' => Str::limit($this->safeName($file->getClientOriginalName()), 250, ''),
            'file_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
        ];
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

    /**
     * Stream a file as a download (or inline for images when $inline).
     */
    public function response(string $path, string $name, string $mime, bool $inline = false): StreamedResponse
    {
        abort_unless($this->exists($path), 404);

        return $this->disk()->response($path, $name, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name) ?? 'file';

        return trim($name) === '' ? 'file' : $name;
    }
}
