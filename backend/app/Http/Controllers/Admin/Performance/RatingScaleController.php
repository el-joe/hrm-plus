<?php

namespace App\Http\Controllers\Admin\Performance;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Enums\PerformanceCycleStatus;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Performance\RatingScaleRequest;
use App\Models\RatingScale;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Rating scales and their levels. Once a launched cycle uses a scale, its level
 * values and thresholds are locked (reviews point at the levels); labels, colours
 * and descriptions stay editable. Scales in use are deactivated, not deleted.
 */
class RatingScaleController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', RatingScale::class);

        $filters = $this->filters($request);

        return view('admin.performance.rating-scales.index', [
            'records' => $this->applyFilters(RatingScale::query()->with('levels')->withCount('cycles'), $filters)
                ->orderBy('code')->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', RatingScale::class);

        return $this->form(new RatingScale(['is_active' => true]));
    }

    public function store(RatingScaleRequest $request): RedirectResponse
    {
        $scale = DB::transaction(function () use ($request): RatingScale {
            $scale = RatingScale::create($request->recordData());

            foreach ($request->levels() as $level) {
                $scale->levels()->create($level);
            }

            return $scale;
        });

        return redirect()->route('admin.rating-scales.index')->with('status', __('performance.scales.saved', ['name' => $scale->name]));
    }

    public function edit(RatingScale $ratingScale): View
    {
        Gate::authorize('update', $ratingScale);

        return $this->form($ratingScale);
    }

    public function update(RatingScaleRequest $request, RatingScale $ratingScale): RedirectResponse
    {
        $locked = $this->locked($ratingScale);

        DB::transaction(function () use ($request, $ratingScale, $locked): void {
            $ratingScale->update($request->recordData());
            $existing = $ratingScale->levels()->get()->keyBy(fn ($level): string => (string) $level->value);

            foreach ($request->levels() as $row) {
                $key = bcadd((string) $row['value'], '0', 2);
                $level = $existing->pull($key);

                if ($level !== null) {
                    $level->update($locked ? array_intersect_key($row, array_flip(['label_ar', 'label_en', 'description', 'color'])) : $row);
                } elseif (! $locked) {
                    $ratingScale->levels()->create($row);
                }
            }

            if (! $locked) {
                $ratingScale->levels()->whereKey($existing->pluck('id'))->delete();
            }
        });

        return redirect()->route('admin.rating-scales.index')->with('status', __($locked ? 'performance.scales.saved_locked' : 'performance.scales.saved', ['name' => $ratingScale->name]));
    }

    public function destroy(RatingScale $ratingScale, DataScopeManager $scopes): RedirectResponse
    {
        Gate::authorize('delete', $ratingScale);

        $usages = array_filter($scopes->withoutScoping(fn (): array => $ratingScale->usageCounts()));

        if ($usages !== []) {
            $parts = array_map(fn (string $key, int $count): string => trans_choice('performance.usage.'.$key, $count, ['count' => $count]), array_keys($usages), $usages);

            throw ValidationException::withMessages(['record' => __('performance.messages.in_use', ['name' => $ratingScale->name, 'usages' => implode(__('structure.messages.list_separator'), $parts)])]);
        }

        $ratingScale->delete();

        return redirect()->route('admin.rating-scales.index')->with('status', __('structure.messages.deleted', ['name' => $ratingScale->name]));
    }

    public function status(Request $request, RatingScale $ratingScale, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $ratingScale, $changeStatus);
    }

    private function locked(RatingScale $scale): bool
    {
        return $scale->exists && $scale->cycles()->where('status', '!=', PerformanceCycleStatus::Draft->value)->exists();
    }

    private function form(RatingScale $scale): View
    {
        $levels = $scale->exists ? $scale->levels()->get()->map->only(['value', 'label_ar', 'label_en', 'min_score', 'description', 'color'])->all() : [];

        return view('admin.performance.rating-scales.form', [
            'record' => $scale,
            'levels' => $levels,
            'locked' => $this->locked($scale),
        ]);
    }
}
