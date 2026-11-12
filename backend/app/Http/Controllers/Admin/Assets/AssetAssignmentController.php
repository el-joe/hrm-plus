<?php

namespace App\Http\Controllers\Admin\Assets;

use App\Actions\Assets\ManageAssetCustody;
use App\Enums\AssetAssignmentStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Assets\AssignAssetRequest;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Models\OnboardingTask;
use App\Services\Employees\Spreadsheet;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Asset custody (inside the data scope, through the employee): who holds
 * what, unacknowledged and overdue handovers; the handover form (also
 * reached from the onboarding task "Assign company assets" with
 * ?employee=&onboarding_task=, and from an asset page with ?asset=);
 * returns with a condition check, losses and paper acknowledgements.
 */
class AssetAssignmentController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AssetAssignment::class);

        $filters = $this->filters($request);
        $query = $this->query($filters)->with(['asset' => fn ($query) => $query->withoutGlobalScopes()->with('category'), 'employee']);
        $sort = IndexSorting::apply($query, $request, ['assigned_on' => 'assigned_on', 'expected_return_on' => 'expected_return_on', 'returned_on' => 'returned_on'], 'assigned_on', 'desc');

        $active = AssetAssignment::query()->active();

        return view('admin.assets.assignments.index', [
            'assignments' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'statuses' => AssetAssignmentStatus::cases(),
            'categories' => AssetCategory::query()->orderByName()->get(),
            'counts' => [
                'active' => (clone $active)->count(),
                'unacknowledged' => (clone $active)->whereNull('acknowledged_at')->count(),
                'overdue' => (clone $active)->whereNotNull('expected_return_on')->where('expected_return_on', '<', today()->toDateString())->count(),
                'holders' => (clone $active)->distinct()->count('employee_id'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', AssetAssignment::class);

        $task = $request->integer('onboarding_task') ? OnboardingTask::query()->find($request->integer('onboarding_task')) : null;

        return view('admin.assets.assignments.create', [
            'assets' => Asset::query()->where('status', AssetStatus::Available)->with('category')->orderBy('asset_tag')->get(),
            'employees' => Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'conditions' => AssetCondition::cases(),
            'selectedAsset' => $request->integer('asset') ?: null,
            'selectedEmployee' => $request->integer('employee') ?: null,
            'task' => $task,
        ]);
    }

    public function store(AssignAssetRequest $request, ManageAssetCustody $custody): RedirectResponse
    {
        $asset = $request->asset();
        $employee = $request->employee();

        if ($asset === null) {
            throw ValidationException::withMessages(['asset_id' => __('assets.validation.asset_required')]);
        }

        if ($employee === null) {
            throw ValidationException::withMessages(['employee_id' => __('assets.validation.employee_required')]);
        }

        Gate::authorize('assign', $asset);
        abort_if($employee->id === $request->user()->employee_id, 403);

        $custody->assign($asset, $employee, $request->handover(), $request->user(), $request->onboardingTask());

        return redirect()->route('admin.assets.show', $asset)->with('status', __('assets.messages.assigned', ['tag' => $asset->asset_tag, 'name' => $employee->name]));
    }

    public function recordReturn(Request $request, AssetAssignment $assignment, ManageAssetCustody $custody): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $data = $request->validate([
            'returned_on' => ['required', 'date', 'before_or_equal:today'],
            'condition_on_return' => ['required', Rule::enum(AssetCondition::class)],
            'return_notes' => [$request->input('condition_on_return') === AssetCondition::Damaged->value ? 'required' : 'nullable', 'string', 'max:2000'],
        ]);
        $custody->returnAsset($assignment, $data, $request->user());

        return back()->with('status', __('assets.messages.returned', ['tag' => $assignment->asset()->withoutGlobalScopes()->value('asset_tag')]));
    }

    public function acknowledge(Request $request, AssetAssignment $assignment, ManageAssetCustody $custody): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $custody->acknowledge($assignment, 'admin', $request->user(), $note);

        return back()->with('status', __('assets.messages.acknowledged_recorded'));
    }

    public function lost(Request $request, AssetAssignment $assignment, ManageAssetCustody $custody): RedirectResponse
    {
        Gate::authorize('update', $assignment);

        $data = $request->validate([
            'reported_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['required', 'string', 'max:2000'],
        ]);
        $custody->markLost($assignment, $data['reported_on'], $data['note'], $request->user());

        return back()->with('status', __('assets.messages.lost'));
    }

    public function export(Request $request, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('export', AssetAssignment::class);

        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/asset-custody-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('assets.fields.'.$key), [
            'asset_tag', 'name', 'asset_category_id', 'employee', 'assigned_on', 'condition_on_issue', 'acknowledged_at', 'expected_return_on',
            'returned_on', 'condition_on_return', 'status',
        ]);

        $rows = $this->query($filters)->with(['asset' => fn ($query) => $query->withoutGlobalScopes()->with('category'), 'employee'])
            ->orderByDesc('assigned_on')->lazy()->map(fn (AssetAssignment $row): array => [
                $row->asset->asset_tag, $row->asset->name, $row->asset->category?->name, $row->employee->label(), $row->assigned_on->toDateString(),
                $row->condition_on_issue->label(), $row->acknowledged_at?->toDateString() ?? '', $row->expected_return_on?->toDateString() ?? '',
                $row->returned_on?->toDateString() ?? '', $row->condition_on_return?->label() ?? '', $row->status->label(),
            ]);

        $spreadsheet->write($path, $format, $headers, $rows);

        return response()->download($path, 'asset-custody-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(AssetAssignmentStatus::class)],
            'category' => ['nullable', 'integer'],
            'flag' => ['nullable', 'in:unacknowledged,overdue'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AssetAssignment>
     */
    private function query(array $filters): Builder
    {
        return AssetAssignment::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->whereHas('employee', fn (Builder $employee) => $employee->search($search))
                ->orWhereHas('asset', fn (Builder $asset) => $asset->withoutGlobalScopes()->search($search))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $query, int|string $id) => $query->whereHas('asset', fn (Builder $asset) => $asset->withoutGlobalScopes()->where('asset_category_id', $id)))
            ->when(($filters['flag'] ?? null) === 'unacknowledged', fn (Builder $query) => $query->active()->whereNull('acknowledged_at'))
            ->when(($filters['flag'] ?? null) === 'overdue', fn (Builder $query) => $query->active()->whereNotNull('expected_return_on')->where('expected_return_on', '<', today()->toDateString()));
    }
}
