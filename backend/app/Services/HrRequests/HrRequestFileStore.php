<?php

namespace App\Services\HrRequests;

use App\Models\HrRequest;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Request attachments and issued letters on the private disk "hr_request_files": random names inside a
 * per-request folder, never reachable by URL; streamed by the controllers after the ownership / policy check.
 */
class HrRequestFileStore
{
    public function disk(): Filesystem
    {
        return Storage::disk('hr_request_files');
    }

    /**
     * Validation rules of one uploaded file (settings hr_requests.allowed_extensions / attachment_max_kb).
     *
     * @return list<string>
     */
    public static function fileRules(): array
    {
        $extensions = implode(',', (array) setting('hr_requests.allowed_extensions'));

        return ['file', 'mimes:'.$extensions, 'extensions:'.$extensions, 'max:'.(int) setting('hr_requests.attachment_max_kb')];
    }

    /**
     * @return array{file_path: string, file_name: string, file_mime: string, file_size: int}
     */
    public function store(HrRequest $request, UploadedFile $file): array
    {
        $extension = Str::lower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $this->disk()->putFileAs((string) $request->getKey(), $file, Str::uuid()->toString().'.'.$extension);

        return [
            'file_path' => $path,
            'file_name' => Str::limit($this->safeName($file->getClientOriginalName()), 250, ''),
            'file_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
        ];
    }

    /**
     * Store generated contents (issued letters, demo data).
     *
     * @return array{file_path: string, file_name: string, file_mime: string, file_size: int}
     */
    public function storeContents(HrRequest $request, string $contents, string $name, string $mime): array
    {
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');
        $path = $request->getKey().'/'.Str::uuid()->toString().'.'.$extension;
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
