<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Settings\MobileAppConfig;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    /**
     * What the app needs before sign-in (splash): company identity, languages, display formats,
     * feature switches, password rules and the minimum supported app version.
     *
     * @unauthenticated
     */
    public function show(MobileAppConfig $config): JsonResponse
    {
        return ApiResponse::success($config->toArray());
    }
}
