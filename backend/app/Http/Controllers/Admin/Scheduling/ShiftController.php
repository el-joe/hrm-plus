<?php

namespace App\Http\Controllers\Admin\Scheduling;

use App\Actions\Attendance\SaveShift;
use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Enums\BreakType;
use App\Enums\ShiftType;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Scheduling\ShiftRequest;
use App\Models\Shift;
use App\Support\IndexSorting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Shift definitions (fixed / flexible, breaks, windows, grace overrides).
 */
class ShiftController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Shift::class);

        $filters = $this->filters($request) + $request->validate(['type' => ['nullable', 'in:fixed,flexible']]);
        $query = $this->applyFilters(Shift::query()->with('breaks'), $filters)
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('type', $type));
        $sort = IndexSorting::apply($query, $request, ['code' => 'code', 'name' => Shift::localizedColumn(), 'start' => 'start_time'], default: 'start');

        return view('admin.scheduling.shifts.index', [
            'records' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'sort' => $sort,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Shift::class);

        return $this->form(new Shift([
            'type' => ShiftType::Fixed, 'checkin_opens_before_minutes' => 120, 'checkout_closes_after_minutes' => 240,
            'color' => Shift::COLORS[0], 'is_active' => true,
        ]));
    }

    public function store(ShiftRequest $request, SaveShift $save): RedirectResponse
    {
        $shift = $save->handle(new Shift, $request->shiftData(), $request->breakRows());

        return redirect()->route('admin.shifts.index')->with('status', __('structure.messages.created', ['name' => $shift->name]));
    }

    public function edit(Shift $shift): View
    {
        Gate::authorize('update', $shift);

        return $this->form($shift->load('breaks'));
    }

    public function update(ShiftRequest $request, Shift $shift, SaveShift $save): RedirectResponse
    {
        $save->handle($shift, $request->shiftData(), $request->breakRows());

        return redirect()->route('admin.shifts.index')->with('status', __('structure.messages.updated', ['name' => $shift->name]));
    }

    public function destroy(Shift $shift, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($shift, $delete, 'admin.shifts.index');
    }

    public function status(Request $request, Shift $shift, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $shift, $changeStatus);
    }

    private function form(Shift $shift): View
    {
        return view('admin.scheduling.shifts.form', [
            'record' => $shift,
            'breaks' => $shift->exists ? $shift->breaks : collect(),
            'maxBreaks' => ShiftRequest::MAX_BREAKS,
            'types' => ShiftType::cases(),
            'breakTypes' => BreakType::cases(),
            'colors' => Shift::COLORS,
        ]);
    }
}
