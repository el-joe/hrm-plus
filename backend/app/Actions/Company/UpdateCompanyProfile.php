<?php

namespace App\Actions\Company;

use App\Models\Company;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Saves the company profile and its logo. The logo lives on the "public"
 * disk under company/ and is served by the company.logo route; the previous
 * file is removed only after the new one is stored and the row is saved.
 */
class UpdateCompanyProfile
{
    public const LOGO_DISK = 'public';

    /**
     * @param  array<string, mixed>  $data  validated profile fields
     */
    public function handle(array $data, ?UploadedFile $logo = null, bool $removeLogo = false): Company
    {
        $company = Company::current();
        $previousLogo = $company->logo_path;
        $newLogo = $logo?->store('company', self::LOGO_DISK);

        try {
            DB::transaction(function () use ($company, $data, $newLogo, $removeLogo): void {
                $company->fill($data);

                if ($newLogo !== null) {
                    $company->logo_path = $newLogo;
                } elseif ($removeLogo) {
                    $company->logo_path = null;
                }

                $company->save();
            });
        } catch (\Throwable $exception) {
            if ($newLogo !== null) {
                Storage::disk(self::LOGO_DISK)->delete($newLogo);
            }

            throw $exception;
        }

        if ($previousLogo !== null && $previousLogo !== $company->logo_path) {
            Storage::disk(self::LOGO_DISK)->delete($previousLogo);
        }

        return $company;
    }
}
