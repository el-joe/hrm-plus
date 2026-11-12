<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Assets\ManageAssetCustody;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Assets\PortalAssets;
use App\Services\Employees\PortalProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My assets" (spec section 5 "Assigned company assets"): what the employee
 * holds now (with the handover details) and what they returned; confirming
 * receipt of a new handover. Own record only — another employee's
 * assignment id is a 404.
 */
class AssetController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalAssets $assets) {}

    public function index(Request $request): View
    {
        $assignments = $this->assets->assignments($this->employee($request))->get();

        return view('employee.assets.index', [
            'current' => $assignments->filter->isActive()->values(),
            'past' => $assignments->reject->isActive()->values(),
        ]);
    }

    public function acknowledge(Request $request, int $assignment, ManageAssetCustody $custody): RedirectResponse
    {
        $record = $this->assets->find($this->employee($request), $assignment);
        abort_if($record === null, 404);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $custody->acknowledge($record, 'portal', $request->user(), $note);

        return redirect()->route('portal.assets')->with('status', __('assets.portal.acknowledged', ['asset' => $record->asset->label()]));
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
