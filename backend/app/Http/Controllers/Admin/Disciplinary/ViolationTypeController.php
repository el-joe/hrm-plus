<?php

namespace App\Http\Controllers\Admin\Disciplinary;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Enums\DisciplinaryActionType;
use App\Enums\ViolationSeverity;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Disciplinary\ViolationTypeRequest;
use App\Models\ViolationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Violation types catalogue (disciplinary.manage). Types already used by a
 * case are deactivated instead of deleted.
 */
class ViolationTypeController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ViolationType::class);

        $filters = $this->filters($request);
        $records = $this->applyFilters(ViolationType::query()->withCount(['cases' => fn ($query) => $query->withoutGlobalScopes()]), $filters)
            ->orderBy('code')->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.disciplinary.violation-types.index', ['records' => $records, 'filters' => $filters]);
    }

    public function create(): View
    {
        Gate::authorize('create', ViolationType::class);

        return $this->form(new ViolationType(['severity' => ViolationSeverity::Minor, 'default_action' => DisciplinaryActionType::VerbalWarning, 'is_active' => true]));
    }

    public function store(ViolationTypeRequest $request): RedirectResponse
    {
        $type = ViolationType::create($request->recordData());

        return redirect()->route('admin.violation-types.index')->with('status', __('disciplinary.violation_types.saved', ['name' => $type->name]));
    }

    public function edit(ViolationType $violationType): View
    {
        Gate::authorize('update', $violationType);

        return $this->form($violationType);
    }

    public function update(ViolationTypeRequest $request, ViolationType $violationType): RedirectResponse
    {
        $violationType->update($request->recordData());

        return redirect()->route('admin.violation-types.index')->with('status', __('disciplinary.violation_types.saved', ['name' => $violationType->name]));
    }

    public function destroy(ViolationType $violationType): RedirectResponse
    {
        Gate::authorize('delete', $violationType);

        if ($violationType->cases()->withoutGlobalScopes()->exists()) {
            throw ValidationException::withMessages(['record' => __('disciplinary.violation_types.in_use', ['name' => $violationType->name])]);
        }

        $violationType->delete();

        return redirect()->route('admin.violation-types.index')->with('status', __('structure.messages.deleted', ['name' => $violationType->name]));
    }

    public function status(Request $request, ViolationType $violationType, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $violationType, $changeStatus);
    }

    private function form(ViolationType $type): View
    {
        return view('admin.disciplinary.violation-types.form', [
            'record' => $type,
            'severities' => ViolationSeverity::cases(),
            'actionTypes' => DisciplinaryActionType::cases(),
        ]);
    }
}
