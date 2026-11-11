<?php

namespace App\Http\Controllers\Admin\Scheduling;

use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Scheduling\HolidayRequest;
use App\Models\Branch;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Public holidays (company-wide or per branch, ranges, recurring yearly).
 * The attendance engine reads them through HolidayCalendar.
 */
class HolidayController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Holiday::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'branch_id' => ['nullable', 'string', 'max:20'],
        ]);
        $year = (int) ($filters['year'] ?? now()->year);

        $records = Holiday::query()->with('branch')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->search($search))
            ->when($filters['branch_id'] ?? null, fn ($query, string $branch) => $branch === 'company' ? $query->whereNull('branch_id') : $query->where('branch_id', (int) $branch))
            ->where(fn ($query) => $query->where('is_recurring', true)->whereYear('start_date', '<=', $year)
                ->orWhere(fn ($query) => $query->whereYear('start_date', '<=', $year)->whereYear('end_date', '>=', $year)))
            ->orderByRaw('MONTH(start_date), DAY(start_date)')->orderBy('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.scheduling.holidays.index', [
            'records' => $records,
            'filters' => $filters,
            'year' => $year,
            'branches' => Branch::query()->orderBy('code')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Holiday::class);

        return $this->form(new Holiday(['is_paid' => true, 'is_active' => true]));
    }

    public function store(HolidayRequest $request): RedirectResponse
    {
        $holiday = Holiday::create($request->recordData());

        return redirect()->route('admin.holidays.index', ['year' => $holiday->start_date->year])->with('status', __('schedules.holidays.saved', ['name' => $holiday->name]));
    }

    public function edit(Holiday $holiday): View
    {
        Gate::authorize('update', $holiday);

        return $this->form($holiday);
    }

    public function update(HolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($request->recordData());

        return redirect()->route('admin.holidays.index', ['year' => $holiday->start_date->year])->with('status', __('schedules.holidays.saved', ['name' => $holiday->name]));
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        Gate::authorize('delete', $holiday);

        $holiday->delete();

        return redirect()->route('admin.holidays.index')->with('status', __('schedules.holidays.deleted', ['name' => $holiday->name]));
    }

    public function status(Request $request, Holiday $holiday, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $holiday, $changeStatus);
    }

    private function form(Holiday $holiday): View
    {
        return view('admin.scheduling.holidays.form', [
            'record' => $holiday,
            'branches' => Branch::query()->activeOrKeep($holiday->branch_id)->orderBy('code')->get(),
        ]);
    }
}
