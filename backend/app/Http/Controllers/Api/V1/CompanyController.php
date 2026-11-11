<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CompanyResource;
use App\Models\Company;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CompanyController extends Controller
{
    public function show(): JsonResponse
    {
        return ApiResponse::success(new CompanyResource(Company::current()));
    }
}
