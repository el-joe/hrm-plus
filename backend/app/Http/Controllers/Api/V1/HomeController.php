<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\HomeSummaryResource;
use App\Services\Employees\PortalProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every card of the app's home screen in one call; always the token owner's own data.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, PortalProfile $profile): JsonResponse
    {
        $employee = $profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return ApiResponse::success((new HomeSummaryResource($employee))->forUser($request->user()));
    }
}
