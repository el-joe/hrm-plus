<?php

namespace App\Services\Payroll;

use App\Services\Employees\EmployeeFileStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Supporting documents of allowance / deduction entries on the private
 * employee files disk, under "payroll/entries" (an entry may target a whole
 * department, so files are not kept in one employee's folder). Never public;
 * streamed after the policy check with response() of EmployeeFileStore.
 */
class PayrollFileStore extends EmployeeFileStore
{
    public const FOLDER = 'payroll/entries';

    /**
     * @return array{file_path: string, file_name: string, file_mime: string, file_size: int}
     */
    public function storeEntryFile(UploadedFile $file): array
    {
        $extension = Str::lower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $path = $this->disk()->putFileAs(self::FOLDER, $file, Str::uuid()->toString().'.'.$extension);
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $file->getClientOriginalName()) ?? 'file';

        return [
            'file_path' => $path,
            'file_name' => Str::limit(trim($name) === '' ? 'file' : $name, 250, ''),
            'file_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
        ];
    }
}
