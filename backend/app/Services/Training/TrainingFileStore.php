<?php

namespace App\Services\Training;

use App\Models\TrainingCertificate;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Certificate files on the private disk "training_files", under random names in a
 * per-employee folder. Never reachable by URL: callers stream them after their own
 * authorisation check. Upload rules follow Settings → Documents.
 */
class TrainingFileStore
{
    public const DISK = 'training_files';

    public function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    /**
     * Validation rules of an uploaded certificate file (Settings → Documents).
     *
     * @return list<string>
     */
    public static function rules(): array
    {
        $extensions = implode(',', (array) setting('documents.allowed_extensions'));

        return ['file', 'max:'.(int) setting('documents.max_upload_size_kb'), 'mimes:'.$extensions, 'extensions:'.$extensions];
    }

    /**
     * @return array{file_path: string, file_name: string, file_mime: string, file_size: int}
     */
    public function store(int $employeeId, UploadedFile $file): array
    {
        $extension = Str::lower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $this->disk()->putFileAs($employeeId.'/certificates', $file, Str::uuid()->toString().'.'.$extension);

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
    public function storeContents(int $employeeId, string $contents, string $name, string $mime): array
    {
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION) ?: 'bin');
        $path = $employeeId.'/certificates/'.Str::uuid()->toString().'.'.$extension;
        $this->disk()->put($path, $contents);

        return ['file_path' => $path, 'file_name' => $this->safeName($name), 'file_mime' => $mime, 'file_size' => strlen($contents)];
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            $this->disk()->delete($path);
        }
    }

    public function response(TrainingCertificate $certificate): StreamedResponse
    {
        abort_unless($certificate->file_path !== null && $this->disk()->exists($certificate->file_path), 404);

        return $this->disk()->response($certificate->file_path, $certificate->file_name ?? 'certificate', [
            'Content-Type' => $certificate->file_mime ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], 'attachment');
    }

    private function safeName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name) ?? 'file';

        return trim($name) === '' ? 'file' : $name;
    }
}
