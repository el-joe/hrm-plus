<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Actions\Payroll\ManagePayrollPeriod;
use App\Enums\PayrollPeriodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payroll\PayrollPeriodRequest;
use App\Models\PayrollPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Payroll periods: list with their runs, new monthly / custom period, pay
 * date and cut-off edits, close / reopen. Rules in ManagePayrollPeriod.
 */
class PayrollPeriodController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PayrollPeriod::class);

        return view('admin.payroll.periods.index', [
            'periods' => PayrollPeriod::query()->withCount(['runs' => fn ($query) => $query->withoutGlobalScopes()])->orderByDesc('start_date')->paginate(24),
            'types' => PayrollPeriodType::cases(),
            'nextMonth' => $this->nextMonth(),
        ]);
    }

    public function store(PayrollPeriodRequest $request, ManagePayrollPeriod $periods): RedirectResponse
    {
        $data = $request->validated();
        $period = $data['type'] === PayrollPeriodType::Monthly->value
            ? $this->monthly($periods, $data, $request)
            : $periods->store($data, $request->user());

        return redirect()->route('admin.payroll-periods.index')->with('status', __('payroll_runs.periods.created', ['name' => $period->name]));
    }

    public function update(PayrollPeriodRequest $request, PayrollPeriod $payrollPeriod, ManagePayrollPeriod $periods): RedirectResponse
    {
        $periods->update($payrollPeriod, $request->validated(), $request->user());

        return back()->with('status', __('payroll_runs.periods.updated'));
    }

    public function close(Request $request, PayrollPeriod $payrollPeriod, ManagePayrollPeriod $periods): RedirectResponse
    {
        Gate::authorize('manage', $payrollPeriod);
        $periods->close($payrollPeriod, $request->user());

        return back()->with('status', __('payroll_runs.periods.closed'));
    }

    public function reopen(Request $request, PayrollPeriod $payrollPeriod, ManagePayrollPeriod $periods): RedirectResponse
    {
        Gate::authorize('manage', $payrollPeriod);
        $periods->reopen($payrollPeriod, $request->user());

        return back()->with('status', __('payroll_runs.periods.reopened'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function monthly(ManagePayrollPeriod $periods, array $data, Request $request): PayrollPeriod
    {
        if (PayrollPeriod::query()->where('type', PayrollPeriodType::Monthly)->where('code', $data['month'])->exists()) {
            throw ValidationException::withMessages(['month' => __('payroll_runs.validation.period_code')]);
        }

        return $periods->monthly($data['month'], $request->user(), $data['pay_date'] ?? null, $data['cutoff_date'] ?? null);
    }

    private function nextMonth(): string
    {
        $latest = PayrollPeriod::query()->where('type', PayrollPeriodType::Monthly)->max('start_date');

        return $latest ? now()->parse($latest)->addMonthNoOverflow()->format('Y-m') : now()->format('Y-m');
    }
}
