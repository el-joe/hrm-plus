<?php

namespace App\Http\Controllers\Admin\Disciplinary;

use App\Actions\Disciplinary\ManageDisciplinaryAction;
use App\Enums\DisciplinaryActionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Disciplinary\ProposeActionRequest;
use App\Models\DisciplinaryAction;
use App\Models\DisciplinaryCase;
use App\Services\Disciplinary\WarningEscalation;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Propose, decide and withdraw the outcome of a disciplinary case. Rules live in
 * App\Actions\Disciplinary\ManageDisciplinaryAction; decisions go through the
 * "disciplinary_action" approval workflow.
 */
class DisciplinaryActionController extends Controller
{
    public function create(Request $request, DisciplinaryCase $disciplinaryCase, WarningEscalation $escalation, SensitiveDataAccessLog $accessLog): View
    {
        Gate::authorize('update', $disciplinaryCase);
        abort_unless($disciplinaryCase->status->acceptsProposal() && $disciplinaryCase->pendingAction() === null, 404);

        $employee = $disciplinaryCase->employee;
        $accessLog->record(SensitiveDataAccessLog::DISCIPLINARY, $disciplinaryCase, ['active_warnings'], ['case' => $disciplinaryCase->reference], $request->user());

        return view('admin.disciplinary.actions.create', [
            'case' => $disciplinaryCase->load(['employee', 'violationType']),
            'suggestion' => $escalation->suggest($employee, $disciplinaryCase->violationType),
            'activeWarnings' => $escalation->activeWarnings($employee),
            'types' => DisciplinaryActionType::cases(),
            'currency' => (string) setting('localisation.currency_code'),
            'validityMonths' => (int) setting('disciplinary.warning_validity_months'),
        ]);
    }

    public function store(ProposeActionRequest $request, DisciplinaryCase $disciplinaryCase, ManageDisciplinaryAction $actions): RedirectResponse
    {
        $action = $actions->propose($disciplinaryCase, $request->validated(), $request->user());

        return redirect()->route('admin.disciplinary-cases.show', $disciplinaryCase)
            ->with('status', __('disciplinary.actions.proposed', ['type' => $action->type->label()]));
    }

    public function decide(Request $request, DisciplinaryCase $disciplinaryCase, DisciplinaryAction $action, string $decision, ManageDisciplinaryAction $actions): RedirectResponse
    {
        abort_unless($action->disciplinary_case_id === $disciplinaryCase->id, 404);
        Gate::authorize('decide', $action);

        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $actions->decide($action, $request->user(), $decision === 'approve', $data['reason'] ?? null);

        return back()->with('status', __($decision === 'approve' ? 'disciplinary.actions.approved' : 'disciplinary.actions.rejected'));
    }

    public function cancel(Request $request, DisciplinaryCase $disciplinaryCase, DisciplinaryAction $action, ManageDisciplinaryAction $actions): RedirectResponse
    {
        abort_unless($action->disciplinary_case_id === $disciplinaryCase->id, 404);
        Gate::authorize('cancel', $action);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $actions->cancel($action, $request->user(), $data['reason']);

        return back()->with('status', __('disciplinary.actions.cancelled'));
    }
}
