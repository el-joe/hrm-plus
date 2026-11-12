<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Assets\ManageAssetCustody;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\AssetAssignmentResource;
use App\Models\AssetAssignment;
use App\Models\Employee;
use App\Services\Assets\PortalAssets;
use App\Services\Employees\PortalProfile;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET  /api/v1/me/assets                      current custody ("current") and returned / lost ("history")
 * GET  /api/v1/me/assets/{id}                 one assignment with the asset and its handover / return details
 * POST /api/v1/me/assets/{id}/acknowledge     confirm receipt ({note?}); 422 when already acknowledged or closed
 *
 * Always the token owner's own employee record (another employee's id is a 404).
 */
class AssetController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalAssets $assets) {}

    public function index(Request $request): JsonResponse
    {
        $assignments = $this->assets->assignments($this->employee($request))->get();
        $current = $assignments->filter->isActive()->values();

        return ApiResponse::success([
            'current' => AssetAssignmentResource::collection($current)->resolve(),
            'history' => AssetAssignmentResource::collection($assignments->reject->isActive()->values())->resolve(),
        ], meta: [
            'unacknowledged' => $current->reject->isAcknowledged()->count(),
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function show(Request $request, int $assignment): JsonResponse
    {
        return ApiResponse::success(new AssetAssignmentResource($this->find($request, $assignment)));
    }

    public function acknowledge(Request $request, int $assignment, ManageAssetCustody $custody): JsonResponse
    {
        $record = $this->find($request, $assignment);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $custody->acknowledge($record, 'api', $request->user(), $note);

        return ApiResponse::success(new AssetAssignmentResource($this->find($request, $assignment)), __('assets.portal.acknowledged', ['asset' => $record->asset->label()]));
    }

    private function find(Request $request, int $id): AssetAssignment
    {
        $assignment = $this->assets->find($this->employee($request), $id);
        abort_if($assignment === null, 404);

        return $assignment;
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
