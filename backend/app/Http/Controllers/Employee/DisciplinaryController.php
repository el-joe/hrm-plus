<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Disciplinary\ManageDisciplinaryAction;
use App\Http\Controllers\Controller;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use App\Services\Disciplinary\PortalDisciplinary;
use App\Services\Employees\PortalProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Disciplinary notices": the actions issued to the signed-in employee (never
 * pending proposals, investigation notes, evidence or the reporter),
 * acknowledgement of receipt and a written response (once). Own record only.
 */
class DisciplinaryController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalDisciplinary $notices) {}

    public function index(Request $request): View
    {
        return view('employee.disciplinary.index', [
            'notices' => $this->notices->notices($this->employee($request))->paginate(15)->withQueryString(),
        ]);
    }

    public function show(Request $request, int $action): View
    {
        return view('employee.disciplinary.show', ['notice' => $this->find($request, $action)]);
    }

    public function acknowledge(Request $request, int $action, ManageDisciplinaryAction $actions): RedirectResponse
    {
        $actions->acknowledge($this->find($request, $action), $request->user());

        return back()->with('status', __('disciplinary.portal.acknowledged'));
    }

    public function respond(Request $request, int $action, ManageDisciplinaryAction $actions): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', 'string', 'min:3', 'max:5000']]);
        $actions->respond($this->find($request, $action), $request->user(), $data['response']);

        return back()->with('status', __('disciplinary.portal.responded'));
    }

    private function find(Request $request, int $id): DisciplinaryAction
    {
        $notice = $this->notices->find($this->employee($request), $id);
        abort_if($notice === null, 404);

        return $notice;
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
