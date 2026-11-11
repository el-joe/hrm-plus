<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Actions\Payroll\ManagePayrollRun;
use App\Enums\PayrollAdjustmentSource;
use App\Enums\PayrollAdjustmentStatus;
use App\Enums\PayrollRunType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payroll\PayrollAdjustmentRequest;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Services\Payroll\PayrollCorrections;
use App\Services\Payroll\PayrollFormOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Post-finalisation corrections: the queue of pending / applied / dismissed
 * adjustments, "scan now" (attendance and leave changes after finalisation),
 * manual adjustments, dismissal and "create correction run" (an off-cycle
 * run of everyone with pending adjustments in an open period).
 */
class PayrollCorrectionController extends Controller
{
    public function index(Request $request, PayrollFormOptions $options): View
    {
        Gate::authorize('viewAny', PayrollAdjustment::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(PayrollAdjustmentStatus::class)],
            'source' => ['nullable', Rule::enum(PayrollAdjustmentSource::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $filters['status'] ??= PayrollAdjustmentStatus::Pending->value;

        return view('admin.payroll.corrections.index', [
            'adjustments' => PayrollAdjustment::query()->with(['employee', 'originalPeriod', 'carrier.run', 'creator'])
                ->where('status', $filters['status'])
                ->when($filters['source'] ?? null, fn (Builder $query, string $source) => $query->where('source', $source))
                ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query->whereLike('reference', "%{$term}%")->orWhereHas('employee', fn (Builder $employee) => $employee->search($term))))
                ->latest('id')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'statuses' => PayrollAdjustmentStatus::cases(),
            'sources' => PayrollAdjustmentSource::cases(),
            'employees' => $options->employees()->reject(fn ($employee): bool => $employee->id === $request->user()->employee_id),
            'components' => SalaryComponent::query()->active()->whereIn('kind', ['earning', 'deduction'])->ordered()->get(),
            'periods' => PayrollPeriod::query()->orderByDesc('start_date')->get(),
            'openPeriods' => PayrollPeriod::query()->open()->orderByDesc('start_date')->get(),
            'pendingFree' => PayrollAdjustment::query()->pending()->whereNull('payroll_item_id')->count(),
        ]);
    }

    public function store(PayrollAdjustmentRequest $request, PayrollCorrections $corrections): RedirectResponse
    {
        $adjustment = $corrections->manual($request->employee(), $request->validated(), $request->user());

        return back()->with('status', __('payroll_runs.corrections.created', ['reference' => $adjustment->reference]));
    }

    public function scan(Request $request, PayrollCorrections $corrections): RedirectResponse
    {
        Gate::authorize('create', PayrollAdjustment::class);
        $created = $corrections->scan($request->user());

        return back()->with('status', __('payroll_runs.corrections.scanned', ['count' => $created]));
    }

    public function dismiss(Request $request, PayrollAdjustment $adjustment, PayrollCorrections $corrections): RedirectResponse
    {
        Gate::authorize('dismiss', $adjustment);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $corrections->dismiss($adjustment, $data['reason'], $request->user());

        return back()->with('status', __('payroll_runs.corrections.dismissed'));
    }

    public function run(Request $request, ManagePayrollRun $runs): RedirectResponse
    {
        Gate::authorize('create', PayrollRun::class);
        $data = $request->validate(['payroll_period_id' => ['required', 'integer', Rule::exists('payroll_periods', 'id')]]);
        $run = $runs->create(PayrollPeriod::query()->findOrFail($data['payroll_period_id']), PayrollRunType::Correction, ['all' => true], $request->user());

        return redirect()->route('admin.payroll-runs.show', $run)->with('status', __('payroll_runs.runs.created', ['reference' => $run->reference, 'count' => $run->employee_count]));
    }
}
