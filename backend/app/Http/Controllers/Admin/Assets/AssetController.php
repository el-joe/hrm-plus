<?php

namespace App\Http\Controllers\Admin\Assets;

use App\Actions\Assets\ChangeAssetStatus;
use App\Actions\Assets\SaveAsset;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Assets\AssetRequest;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Employee;
use App\Services\Employees\Spreadsheet;
use App\Support\IndexSorting;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The asset register (inside the data scope): list with filters and totals,
 * register / edit, the asset page (custodian, custody history, events) and
 * status changes. Custody itself is handled by AssetAssignmentController.
 */
class AssetController extends Controller
{
    public const PER_PAGE = 25;

    /** Warranty filter "expiring": ends within this many days. */
    public const WARRANTY_SOON_DAYS = 60;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Asset::class);

        $filters = $this->filters($request);
        $query = $this->query($filters)->with(['category', 'branch', 'currentAssignment.employee']);
        $sort = IndexSorting::apply($query, $request, [
            'tag' => 'asset_tag', 'name' => 'name', 'purchase_date' => 'purchase_date', 'cost' => 'purchase_cost', 'warranty' => 'warranty_expires_on',
        ], 'tag');

        $all = Asset::query();
        $inUse = [AssetStatus::Available, AssetStatus::Assigned, AssetStatus::InMaintenance, AssetStatus::Damaged];

        return view('admin.assets.index', [
            'assets' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
            'categories' => AssetCategory::query()->orderByName()->get(),
            'branches' => Branch::query()->orderByName()->get(),
            'statuses' => AssetStatus::cases(),
            'counts' => [
                'total' => (clone $all)->whereIn('status', $inUse)->count(),
                'assigned' => (clone $all)->where('status', AssetStatus::Assigned)->count(),
                'available' => (clone $all)->where('status', AssetStatus::Available)->count(),
                'attention' => (clone $all)->whereIn('status', [AssetStatus::InMaintenance, AssetStatus::Damaged, AssetStatus::Lost])->count(),
                'value' => bcadd((string) ((clone $all)->whereIn('status', $inUse)->sum('purchase_cost') ?? '0'), '0', 2),
            ],
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Asset::class);

        return view('admin.assets.form', [
            'asset' => new Asset(['condition' => AssetCondition::New]),
            ...$this->formOptions(null),
        ]);
    }

    public function store(AssetRequest $request, SaveAsset $save): RedirectResponse
    {
        $asset = $save->handle(null, $request->assetData(), $request->user());

        return redirect()->route('admin.assets.show', $asset)->with('status', __('assets.messages.registered', ['tag' => $asset->asset_tag]));
    }

    public function show(Request $request, Asset $asset, ChangeAssetStatus $status): View
    {
        Gate::authorize('view', $asset);

        $asset->load([
            'category', 'branch', 'currentAssignment.employee', 'currentAssignment.issuer',
            'assignments' => fn ($query) => $query->withoutGlobalScopes()->with(['employee' => fn ($query) => $query->withoutGlobalScopes(), 'issuer', 'receiver']),
            'events' => fn ($query) => $query->with(['creator', 'employee' => fn ($query) => $query->withoutGlobalScopes()]),
        ]);

        $canUpdate = $request->user()->can('update', $asset);

        return view('admin.assets.show', [
            'asset' => $asset,
            'canUpdate' => $canUpdate,
            'canAssign' => $request->user()->can('assign', $asset),
            'canRetire' => $request->user()->can('delete', $asset),
            'statusActions' => $canUpdate ? $status->available($asset) : [],
            'conditions' => AssetCondition::cases(),
            'employees' => $request->user()->can('assign', $asset) ? Employee::query()->employed()->orderBy('employee_number')->get(['id', 'employee_number', 'name_ar', 'name_en']) : collect(),
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function edit(Asset $asset): View
    {
        Gate::authorize('update', $asset);

        return view('admin.assets.form', ['asset' => $asset, ...$this->formOptions($asset)]);
    }

    public function update(AssetRequest $request, Asset $asset, SaveAsset $save): RedirectResponse
    {
        $save->handle($asset, $request->assetData(), $request->user());

        return redirect()->route('admin.assets.show', $asset)->with('status', __('assets.messages.updated', ['tag' => $asset->asset_tag]));
    }

    /**
     * maintenance | maintenance_done | damaged | lost | retire (retire needs assets.delete).
     */
    public function status(Request $request, Asset $asset, string $action, ChangeAssetStatus $status): RedirectResponse
    {
        Gate::authorize($action === 'retire' ? 'delete' : 'update', $asset);

        $data = $request->validate([
            'note' => [in_array($action, ['lost', 'damaged', 'retire'], true) ? 'required' : 'nullable', 'string', 'max:2000'],
            'condition' => ['nullable', Rule::enum(AssetCondition::class)],
        ]);
        $status->handle($asset, $action, $request->user(), $data['note'] ?? null, $action === 'maintenance_done' ? ($data['condition'] ?? null) : null);

        return back()->with('status', __('assets.status_actions.done.'.$action, ['tag' => $asset->asset_tag]));
    }

    public function export(Request $request, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('export', Asset::class);

        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/assets-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('assets.fields.'.$key), [
            'asset_tag', 'asset_category_id', 'name', 'brand', 'model', 'serial_number', 'branch_id', 'status', 'condition',
            'custodian', 'assigned_on', 'purchase_date', 'purchase_cost', 'supplier', 'warranty_expires_on',
        ]);

        $rows = $this->query($filters)->with(['category', 'branch', 'currentAssignment.employee'])->orderBy('asset_tag')->lazy()->map(fn (Asset $asset): array => [
            $asset->asset_tag, $asset->category?->name, $asset->name, $asset->brand, $asset->model, $asset->serial_number, $asset->branch?->name,
            $asset->status->label(), $asset->condition->label(), $asset->currentAssignment?->employee?->label() ?? '',
            $asset->currentAssignment?->assigned_on->toDateString() ?? '', $asset->purchase_date?->toDateString() ?? '',
            $asset->purchase_cost === null ? '' : Money::format((string) $asset->purchase_cost), $asset->supplier, $asset->warranty_expires_on?->toDateString() ?? '',
        ]);

        $spreadsheet->write($path, $format, $headers, $rows);

        return response()->download($path, 'assets-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            'branch' => ['nullable', 'integer'],
            'warranty' => ['nullable', 'in:expiring,expired'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Asset>
     */
    private function query(array $filters): Builder
    {
        return Asset::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search))
            ->when($filters['category'] ?? null, fn (Builder $query, int|string $id) => $query->where('asset_category_id', $id))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['branch'] ?? null, fn (Builder $query, int|string $id) => $query->where('branch_id', $id))
            ->when(($filters['warranty'] ?? null) === 'expiring', fn (Builder $query) => $query
                ->whereBetween('warranty_expires_on', [today()->toDateString(), today()->addDays(self::WARRANTY_SOON_DAYS)->toDateString()]))
            ->when(($filters['warranty'] ?? null) === 'expired', fn (Builder $query) => $query->where('warranty_expires_on', '<', today()->toDateString()));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?Asset $asset): array
    {
        return [
            'categories' => AssetCategory::query()->activeOrKeep($asset?->asset_category_id)->orderByName()->get(),
            'branches' => Branch::query()->activeOrKeep($asset?->branch_id)->orderByName()->get(),
            'conditions' => AssetCondition::cases(),
        ];
    }
}
