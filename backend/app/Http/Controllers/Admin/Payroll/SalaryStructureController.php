<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Actions\Payroll\AssignSalaryStructure;
use App\Actions\Payroll\SaveSalaryStructureVersion;
use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Enums\SalaryStructureScope;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Payroll\SalaryStructureRequest;
use App\Http\Requests\Admin\Payroll\StructureAssignmentRequest;
use App\Http\Requests\Admin\Payroll\StructureVersionRequest;
use App\Models\Employee;
use App\Models\SalaryStructure;
use App\Models\SalaryStructureAssignment;
use App\Models\SalaryStructureLine;
use App\Services\Payroll\PayrollFormOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Salary structures: identity, effective-dated versions of their component
 * lines (a change is always a new version) and assignments to employees,
 * job grades, departments, branches or the company.
 */
class SalaryStructureController extends StructureController
{
    public const EXTRA_LINES = 3;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SalaryStructure::class);

        $filters = $this->filters($request);
        $today = now()->toDateString();
        $records = $this->applyFilters(SalaryStructure::query()->withCount([
            'versions', 'assignments as current_assignments_count' => fn ($query) => $query->effectiveOn($today),
        ]), $filters)->orderBy('code')->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.payroll.structures.index', ['records' => $records, 'filters' => $filters, 'today' => $today]);
    }

    public function create(PayrollFormOptions $options): View
    {
        Gate::authorize('create', SalaryStructure::class);

        return view('admin.payroll.structures.create', [
            'record' => new SalaryStructure(['is_active' => true]),
            'components' => $options->structureComponents(),
            'lines' => $this->blankLines([]),
            'effectiveFrom' => now()->startOfMonth()->toDateString(),
        ]);
    }

    public function store(SalaryStructureRequest $request, SaveSalaryStructureVersion $save): RedirectResponse
    {
        $structure = $save->create($request->structureData(), $request->versionData(), $request->user());

        return redirect()->route('admin.salary-structures.show', $structure)->with('status', __('payroll.structures.saved', ['name' => $structure->name]));
    }

    public function show(SalaryStructure $salaryStructure, PayrollFormOptions $options): View
    {
        Gate::authorize('view', $salaryStructure);

        $versions = $salaryStructure->versions()->with(['lines.component', 'lines.baseComponent', 'creator'])->get();
        $assignments = $salaryStructure->assignments()->with('creator')->orderByDesc('effective_from')->orderByDesc('id')->get()
            ->filter(fn (SalaryStructureAssignment $row): bool => $this->assignmentVisible($row))->values();

        return view('admin.payroll.structures.show', [
            'record' => $salaryStructure,
            'versions' => $versions,
            'current' => $salaryStructure->versionOn(now()),
            'assignments' => $assignments,
            'scopes' => SalaryStructureScope::cases(),
            'targetOptions' => $options->targets(),
            'today' => now()->toDateString(),
        ]);
    }

    public function edit(SalaryStructure $salaryStructure): View
    {
        Gate::authorize('update', $salaryStructure);

        return view('admin.payroll.structures.edit', ['record' => $salaryStructure]);
    }

    public function update(SalaryStructureRequest $request, SalaryStructure $salaryStructure): RedirectResponse
    {
        $salaryStructure->update($request->structureData());

        return redirect()->route('admin.salary-structures.show', $salaryStructure)->with('status', __('payroll.structures.saved', ['name' => $salaryStructure->name]));
    }

    public function destroy(SalaryStructure $salaryStructure, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($salaryStructure, $delete, 'admin.salary-structures.index');
    }

    public function status(Request $request, SalaryStructure $salaryStructure, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $salaryStructure, $changeStatus);
    }

    public function createVersion(SalaryStructure $salaryStructure, PayrollFormOptions $options): View
    {
        Gate::authorize('update', $salaryStructure);

        $latest = $salaryStructure->latestVersion()?->load('lines');
        $existing = $latest ? $latest->lines->map(fn (SalaryStructureLine $line): array => $line->only(['salary_component_id', 'amount', 'rate', 'base_component_id', 'sort_order']))->all() : [];
        $nextStart = $latest && $latest->effective_from->greaterThanOrEqualTo(now()->startOfMonth())
            ? $latest->effective_from->addMonthNoOverflow()->startOfMonth()
            : now()->addMonthNoOverflow()->startOfMonth();

        return view('admin.payroll.structures.version', [
            'record' => $salaryStructure,
            'latest' => $latest,
            'components' => $options->structureComponents(array_column($existing, 'salary_component_id')),
            'lines' => $this->blankLines($existing),
            'effectiveFrom' => $nextStart->toDateString(),
        ]);
    }

    public function storeVersion(StructureVersionRequest $request, SalaryStructure $salaryStructure, SaveSalaryStructureVersion $save): RedirectResponse
    {
        $version = $save->addVersion($salaryStructure, $request->versionData(), $request->user());

        return redirect()->route('admin.salary-structures.show', $salaryStructure)
            ->with('status', __('payroll.structures.version_saved', ['version' => $version->version, 'date' => $version->effective_from->toDateString()]));
    }

    public function storeAssignment(StructureAssignmentRequest $request, SalaryStructure $salaryStructure, AssignSalaryStructure $assign): RedirectResponse
    {
        $scope = SalaryStructureScope::from($request->validated('scope_type'));
        $assignment = $assign->handle($salaryStructure, $scope, $request->validated('scope_id') ? (int) $request->validated('scope_id') : null,
            $request->validated('effective_from'), $request->validated('reason'), $request->user());

        $back = $request->validated('return_to') === 'employee' && $scope === SalaryStructureScope::Employee
            ? route('admin.employees.tab', [$assignment->scope_id, 'compensation'])
            : route('admin.salary-structures.show', $salaryStructure);

        return redirect($back)->with('status', __('payroll.structures.assigned', ['target' => $assignment->scopeLabel()]));
    }

    /**
     * Assign a structure to one employee from the profile's Compensation tab.
     */
    public function assignToEmployee(Request $request, Employee $employee, AssignSalaryStructure $assign): RedirectResponse
    {
        $data = $request->validate([
            'salary_structure_id' => ['required', 'integer'],
            'effective_from' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $structure = SalaryStructure::query()->findOrFail($data['salary_structure_id']);
        Gate::authorize('update', $structure);
        Gate::authorize('updateFinancial', $employee);

        $assign->handle($structure, SalaryStructureScope::Employee, $employee->id, $data['effective_from'], $data['reason'] ?? null, $request->user());

        return redirect()->route('admin.employees.tab', [$employee, 'compensation'])
            ->with('status', __('payroll.structures.assigned', ['target' => $employee->label()]));
    }

    public function endAssignment(Request $request, SalaryStructureAssignment $assignment, AssignSalaryStructure $assign): RedirectResponse
    {
        $this->authorizeAssignment($assignment);
        $data = $request->validate(['effective_to' => ['required', 'date']]);
        $assign->end($assignment, $data['effective_to'], $request->user());

        return back()->with('status', __('payroll.structures.assignment_ended'));
    }

    public function destroyAssignment(Request $request, SalaryStructureAssignment $assignment, AssignSalaryStructure $assign): RedirectResponse
    {
        $this->authorizeAssignment($assignment);
        $assign->withdraw($assignment, $request->user());

        return back()->with('status', __('payroll.structures.assignment_withdrawn'));
    }

    private function authorizeAssignment(SalaryStructureAssignment $assignment): void
    {
        Gate::authorize('update', $assignment->structure);
        abort_unless($this->assignmentVisible($assignment), 403);

        if ($assignment->scope_type === SalaryStructureScope::Employee) {
            $employee = Employee::query()->find($assignment->scope_id);
            abort_unless($employee !== null && request()->user()->can('updateFinancial', $employee), 403);
        }
    }

    /**
     * Restricted admins see assignments of targets inside their scope (company-wide ones too).
     */
    private function assignmentVisible(SalaryStructureAssignment $assignment): bool
    {
        $user = request()->user();

        if (! $user->hasDataRestrictions() || in_array($assignment->scope_type, [SalaryStructureScope::Company, SalaryStructureScope::JobGrade], true)) {
            return true;
        }

        $record = $assignment->scopeRecord();

        return $record !== null && (! method_exists($record, 'isVisibleTo') || $record->isVisibleTo($user));
    }

    /**
     * Existing lines plus a few empty rows (the form works without JavaScript).
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function blankLines(array $lines): array
    {
        $old = old('lines');

        if (is_array($old)) {
            return array_values($old);
        }

        for ($index = 0; $index < self::EXTRA_LINES; $index++) {
            $lines[] = ['salary_component_id' => null, 'amount' => null, 'rate' => null, 'base_component_id' => null, 'sort_order' => null];
        }

        return $lines;
    }
}
