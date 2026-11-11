<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\BranchResource;
use App\Models\Branch;
use App\Support\ApiListQuery;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Active branches with their active attendance locations (company
 * information every employee may see; data scopes apply to admins only).
 */
class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Branch::query()->active()->with(['locations' => fn ($locations) => $locations->active()->orderBy('id')]);

        $branches = ApiListQuery::for($query, $request)
            ->allowFilters([
                'code',
                'search' => fn (Builder $query, string $value) => $query->search($value),
            ])
            ->allowSorts(['code', 'name_ar', 'name_en'])
            ->defaultSort('code')
            ->paginate();

        return ApiResponse::paginated($branches, BranchResource::class);
    }

    public function show(int $branch): JsonResponse
    {
        $branch = Branch::query()->active()->with(['locations' => fn ($locations) => $locations->active()->orderBy('id')])->findOrFail($branch);

        return ApiResponse::success(new BranchResource($branch));
    }
}
