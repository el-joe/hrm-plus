<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Actions\Payroll\CreateFinalSettlement;
use App\Enums\PayrollRunType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OffboardingCase;
use App\Models\PayrollRun;
use App\Services\Payroll\LoanInstalments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Final settlement of a leaving employee, opened from the offboarding case's
 * "Final financial settlement" item: leave days to encash (within the leave
 * policy's rules), what will be recovered (loans), then an off-cycle run
 * that follows the normal approve → finalise workflow.
 */
class FinalSettlementController extends Controller
{
    public function create(OffboardingCase $offboarding, CreateFinalSettlement $settlement, LoanInstalments $loans): View
    {
        Gate::authorize('view', $offboarding);
        Gate::authorize('create', PayrollRun::class);

        $employee = Employee::query()->withoutGlobalScopes()->findOrFail($offboarding->employee_id);
        $lastDay = ($employee->termination_date ?? $offboarding->last_working_date)->toDateString();

        return view('admin.payroll.final-settlement.create', [
            'case' => $offboarding,
            'employee' => $employee,
            'lastDay' => $lastDay,
            'encashable' => $settlement->encashable($employee, $lastDay),
            'loans' => $loans->dueFor($employee, $lastDay, '2999-12-31'),
            'existing' => PayrollRun::query()->withoutGlobalScopes()->where('offboarding_case_id', $offboarding->id)->where('type', PayrollRunType::FinalSettlement)->latest('id')->first(),
        ]);
    }

    public function store(Request $request, OffboardingCase $offboarding, CreateFinalSettlement $settlement): RedirectResponse
    {
        Gate::authorize('view', $offboarding);
        Gate::authorize('create', PayrollRun::class);
        abort_if($offboarding->employee_id === $request->user()->employee_id, 403);

        $data = $request->validate([
            'encash' => ['nullable', 'array'],
            'encash.*' => ['nullable', 'decimal:0,2', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $run = $settlement->handle($offboarding, $request->user(), $data['encash'] ?? [], $data['notes'] ?? null);

        return redirect()->route('admin.payroll-runs.show', $run)->with('status', __('payroll_runs.final_settlement.created', ['reference' => $run->reference]));
    }
}
