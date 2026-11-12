<?php

namespace App\Http\Controllers\Admin\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Assets\AssetCategoryRequest;
use App\Models\AssetCategory;
use App\Support\IndexSorting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Asset categories (assets.manage). A category in use cannot be deleted; deactivate it instead.
 */
class AssetCategoryController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', AssetCategory::class);

        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:active,inactive']]);
        $query = AssetCategory::query()->withCount('assets')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'));
        $sort = IndexSorting::apply($query, $request, ['code' => 'code', 'name' => AssetCategory::localizedColumn(), 'assets' => 'assets_count'], 'code');

        return view('admin.assets.categories.index', [
            'categories' => $query->paginate(25)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', AssetCategory::class);

        return view('admin.assets.categories.form', ['category' => new AssetCategory(['is_active' => true])]);
    }

    public function store(AssetCategoryRequest $request): RedirectResponse
    {
        $category = AssetCategory::create($request->validated());

        return redirect()->route('admin.asset-categories.index')->with('status', __('assets.categories.saved', ['name' => $category->name]));
    }

    public function edit(AssetCategory $assetCategory): View
    {
        Gate::authorize('update', $assetCategory);

        return view('admin.assets.categories.form', ['category' => $assetCategory]);
    }

    public function update(AssetCategoryRequest $request, AssetCategory $assetCategory): RedirectResponse
    {
        $assetCategory->update($request->validated());

        return redirect()->route('admin.asset-categories.index')->with('status', __('assets.categories.saved', ['name' => $assetCategory->name]));
    }

    public function destroy(AssetCategory $assetCategory): RedirectResponse
    {
        Gate::authorize('delete', $assetCategory);

        if ($assetCategory->assets()->withoutGlobalScopes()->exists()) {
            return back()->with('error', __('assets.categories.in_use', ['name' => $assetCategory->name]));
        }

        $assetCategory->delete();

        return redirect()->route('admin.asset-categories.index')->with('status', __('assets.categories.deleted', ['name' => $assetCategory->name]));
    }
}
