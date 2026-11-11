<?php

namespace App\Http\Controllers;

use App\Actions\Company\UpdateCompanyProfile;
use App\Models\Company;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the company logo (public: it appears on sign-in pages and in the
 * mobile app). Served through a route so no storage symlink is needed.
 */
class CompanyLogoController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $path = Company::current()->logo_path;
        $disk = Storage::disk(UpdateCompanyProfile::LOGO_DISK);

        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, headers: [
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
