<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Enums\PayComponentKind;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\PayrollAdjustment;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Payroll\PayrollRunReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The per-employee breakdown of a run (spec module 9 E "transparent
 * breakdown", F "inspect individual employee calculations before approving"):
 * every line with its source and explanation, the inputs and quantities read,
 * the rule versions used, warnings and the calculation hash. Viewing it is
 * logged (sensitive_access.payroll).
 */
class PayrollItemController extends Controller
{
    public function show(Request $request, PayrollRun $payrollRun, PayrollItem $item, PayrollRunReview $review, SensitiveDataAccessLog $log): View
    {
        Gate::authorize('viewItem', [$payrollRun, $item]);

        $item->load(['lines', 'employee' => fn ($query) => $query->withoutGlobalScopes()->with(['department', 'branch', 'jobTitle']), 'branch', 'department', 'payslip', 'carriedAdjustments']);
        $payrollRun->load('period');
        $log->record(SensitiveDataAccessLog::PAYROLL, $item, ['lines', 'inputs'], ['run' => $payrollRun->reference, 'employee_id' => $item->employee_id], $request->user());

        $siblings = $review->items($payrollRun)->orderBy('id')->pluck('id');
        $position = $siblings->search($item->id);
        $previous = $review->previousNet($payrollRun, [$item->employee_id])[$item->employee_id] ?? null;
        $placementIds = collect($item->quantities['placement'] ?? []);

        return view('admin.payroll.items.show', [
            'run' => $payrollRun,
            'item' => $item,
            'earnings' => $item->lines->where('kind', PayComponentKind::Earning)->values(),
            'deductions' => $item->lines->where('kind', PayComponentKind::Deduction)->values(),
            'employer' => $item->lines->where('kind', PayComponentKind::EmployerContribution)->values(),
            'previous' => $previous,
            'variance' => $review->variance((string) $item->net_pay, $previous['net'] ?? null),
            'prevItem' => $position !== false && $position > 0 ? $siblings[$position - 1] : null,
            'nextItem' => $position !== false && $position < $siblings->count() - 1 ? $siblings[$position + 1] : null,
            'branchNames' => Branch::query()->withoutGlobalScopes()->whereIn('id', $placementIds->pluck('branch_id'))->get()->keyBy('id'),
            'departmentNames' => Department::query()->withoutGlobalScopes()->whereIn('id', $placementIds->pluck('department_id'))->get()->keyBy('id'),
            'pendingAdjustments' => PayrollAdjustment::query()->where('employee_id', $item->employee_id)->pending()->whereNull('payroll_item_id')->count(),
        ]);
    }
}
