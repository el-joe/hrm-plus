<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\LoginHistoryResource;
use App\Support\ApiListQuery;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The signed-in employee's own sign-in history. Also the reference
 * implementation of the list conventions (filter / sort / pagination).
 */
class LoginHistoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logins = ApiListQuery::for($request->user()->loginHistories()->getQuery(), $request)
            ->allowFilters([
                'area',
                'successful' => fn (Builder $query, string $value) => $query->where('successful', filter_var($value, FILTER_VALIDATE_BOOLEAN)),
            ])
            ->allowSorts(['created_at'])
            ->defaultSort('-created_at')
            ->paginate();

        return ApiResponse::paginated($logins, LoginHistoryResource::class);
    }
}
