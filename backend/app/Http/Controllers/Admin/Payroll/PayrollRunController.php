<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Actions\Payroll\ManagePayrollRun;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\PayrollRunType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payroll\PayrollRunScopeRequest;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\EmployeeGroup;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\Team;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Payroll\PayrollExports;
use App\Services\Payroll\PayrollFormOptions;
use App\Services\Payroll\PayrollPopulation;
use App\Services\Payroll\PayrollRunReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payroll runs (settlements): list, the wizard (scope → population preview →
 * create), the run page (review with filters and variance, totals by
 * component / branch / department, approvals, history) and every state
 * change. Rules live in App\Actions\Payroll\ManagePayrollRun; figures come
 * from App\Services\Payroll\PayrollRunReview.
 */
class PayrollRunController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PayrollRun::class);

        $filters = $request->validate([
            'period' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(PayrollRunStatus::class)],
            'type' => ['nullable', Rule::enum(PayrollRunType::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $runs = PayrollRun::query()->with(['period', 'branch', 'creator'])
            ->withSum('items as visible_net', 'net_pay')->withCount('items as visible_employees')
            ->when($filters['period'] ?? null, fn (Builder $query, int $period) => $query->where('payroll_period_id', $period))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query->whereLike('reference', "%{$term}%")->orWhereLike('name', "%{$term}%")))
            ->latest('id')->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.payroll.runs.index', [
            'runs' => $runs,
            'filters' => $filters,
            'periods' => PayrollPeriod::query()->orderByDesc('start_date')->get(),
            'statuses' => PayrollRunStatus::cases(),
            'types' => PayrollRunType::cases(),
            'counts' => [
                'pending_approval' => PayrollRun::query()->where('status', PayrollRunStatus::PendingApproval)->count(),
                'in_progress' => PayrollRun::query()->whereIn('status', [PayrollRunStatus::Draft, PayrollRunStatus::Calculated, PayrollRunStatus::Calculating, PayrollRunStatus::Approved])->count(),
                'last_finalised' => PayrollRun::query()->where('status', PayrollRunStatus::Finalised)->where('type', PayrollRunType::Regular)->with('period')->latest('finalised_at')->first(),
            ],
        ]);
    }

    public function create(Request $request, PayrollFormOptions $options): View
    {
        Gate::authorize('create', PayrollRun::class);

        return view('admin.payroll.runs.create', [
            ...$this->scopeOptions($options),
            'periods' => PayrollPeriod::query()->where('status', PayrollPeriodStatus::Open)->orderByDesc('start_date')->get(),
            'types' => PayrollRunType::creatable(),
            'selected' => $request->query(),
        ]);
    }

    /**
     * Wizard step 2: who is in and who is out (GET, so the back button keeps the choices).
     */
    public function preview(PayrollRunScopeRequest $request, PayrollPopulation $population): View
    {
        $period = $request->period();
        $preview = $population->preview($period, $request->type(), $request->scope());

        return view('admin.payroll.runs.preview', [
            'period' => $period,
            'type' => $request->type(),
            'scope' => $population->normalise($request->scope()),
            'summary' => $population->describe($request->scope()),
            'preview' => $preview,
            'names' => $this->placementNames($preview),
            'input' => $request->validated(),
        ]);
    }

    public function store(PayrollRunScopeRequest $request, ManagePayrollRun $runs): RedirectResponse
    {
        $run = $runs->create($request->period(), $request->type(), $request->scope(), $request->user(), $request->validated('name'), $request->validated('notes'));

        return redirect()->route('admin.payroll-runs.show', $run)->with('status', __('payroll_runs.runs.created', ['reference' => $run->reference, 'count' => $run->employee_count]));
    }

    public function show(Request $request, PayrollRun $payrollRun, PayrollRunReview $review): View
    {
        Gate::authorize('view', $payrollRun);

        $tab = in_array($request->query('tab'), ['employees', 'components', 'approvals', 'history'], true) ? $request->query('tab') : 'employees';
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'integer'],
            'department' => ['nullable', 'integer'],
            'flag' => ['nullable', Rule::in(['warnings', 'blocking', 'moved', 'variance', 'failed'])],
        ]);
        $payrollRun->load(['period', 'branch', 'creator', 'calculator', 'submitter', 'finaliser', 'reverser', 'correctsRun', 'reversal', 'offboardingCase',
            'approvalRequests' => fn ($query) => $query->with(['actions.actor', 'currentStep', 'workflow'])->latest('id')]);

        $items = $review->items($payrollRun)->with(['employee' => fn ($query) => $query->withoutGlobalScopes(), 'branch', 'department', 'payslip'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->whereHas('employee', fn (Builder $employee) => $employee->withoutGlobalScopes()->search($term)))
            ->when($filters['branch'] ?? null, fn (Builder $query, int $id) => $query->where('branch_id', $id))
            ->when($filters['department'] ?? null, fn (Builder $query, int $id) => $query->where('department_id', $id))
            ->when(($filters['flag'] ?? null) === 'warnings', fn (Builder $query) => $query->whereRaw('json_length(coalesce(warnings, json_array())) > 0'))
            ->when(($filters['flag'] ?? null) === 'blocking', fn (Builder $query) => $query->where('has_blocking_warning', true))
            ->when(($filters['flag'] ?? null) === 'moved', fn (Builder $query) => $query->where('moved_in_period', true))
            ->when(($filters['flag'] ?? null) === 'failed', fn (Builder $query) => $query->where('status', 'failed'))
            ->orderBy('id')->get();

        $previous = $review->previousNet($payrollRun, $items->pluck('employee_id')->all());
        $variance = $items->mapWithKeys(fn (PayrollItem $item): array => [$item->id => $item->isCalculated()
            ? $review->variance((string) $item->net_pay, $previous[$item->employee_id]['net'] ?? null) : null]);

        if (($filters['flag'] ?? null) === 'variance') {
            $items = $items->filter(fn (PayrollItem $item): bool => $variance[$item->id] !== null && abs((float) $variance[$item->id]) >= 10)->values();
        }

        $user = $request->user();

        return view('admin.payroll.runs.show', [
            'run' => $payrollRun,
            'tab' => $tab,
            'filters' => $filters,
            'totals' => $review->totals($payrollRun),
            'items' => $items,
            'previous' => $previous,
            'variance' => $variance,
            'components' => $tab === 'components' ? $review->byComponent($payrollRun) : collect(),
            'byBranch' => $tab === 'components' ? $review->byPlacement($payrollRun, 'branch_id') : collect(),
            'byDepartment' => $tab === 'components' ? $review->byPlacement($payrollRun, 'department_id') : collect(),
            'history' => $tab === 'history' ? AuditLog::query()->with('user')->where('auditable_type', $payrollRun->getMorphClass())->where('auditable_id', $payrollRun->id)->latest('id')->limit(100)->get() : collect(),
            'branches' => Branch::query()->whereIn('id', $review->items($payrollRun)->select('branch_id'))->orderBy('code')->get(),
            'departments' => Department::query()->whereIn('id', $review->items($payrollRun)->select('department_id'))->orderBy('code')->get(),
            'can' => [
                'update' => $user->can('update', $payrollRun),
                'submit' => $user->can('submit', $payrollRun),
                'cancel' => $user->can('cancel', $payrollRun),
                'decide' => $user->can('decide', $payrollRun),
                'finalise' => $user->can('finalise', $payrollRun),
                'reverse' => $user->can('reverse', $payrollRun),
                'export' => $user->can('export', $payrollRun),
            ],
        ]);
    }

    public function editScope(PayrollRun $payrollRun, PayrollFormOptions $options): View
    {
        Gate::authorize('update', $payrollRun);

        return view('admin.payroll.runs.scope', [...$this->scopeOptions($options), 'run' => $payrollRun->load('period'), 'selected' => ['scope' => $payrollRun->scope]]);
    }

    public function updateScope(PayrollRunScopeRequest $request, PayrollRun $payrollRun, ManagePayrollRun $runs): RedirectResponse
    {
        $runs->updateScope($payrollRun, $request->scope(), $request->user());

        return redirect()->route('admin.payroll-runs.show', $payrollRun)->with('status', __('payroll_runs.runs.scope_updated'));
    }

    public function refresh(Request $request, PayrollRun $payrollRun, ManagePayrollRun $runs): RedirectResponse
    {
        Gate::authorize('update', $payrollRun);
        $runs->refresh($payrollRun, $request->user());

        return back()->with('status', __('payroll_runs.runs.refreshed'));
    }

    public function removeItem(Request $request, PayrollRun $payrollRun, PayrollItem $item, ManagePayrollRun $runs): RedirectResponse
    {
        Gate::authorize('update', $payrollRun);
        abort_unless($item->payroll_run_id === $payrollRun->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $runs->removeEmployee($payrollRun, $item, $data['reason'], $request->user());

        return back()->with('status', __('payroll_runs.runs.employee_removed'));
    }

    public function calculate(Request $request, PayrollRun $payrollRun, ManagePayrollRun $runs): RedirectResponse
    {
        Gate::authorize('update', $payrollRun);
        $run = $runs->calculate($payrollRun, $request->user());

        return back()->with('status', $run->status === PayrollRunStatus::Calculating
            ? __('payroll_runs.runs.calculation_queued', ['count' => $run->progress_total])
            : __('payroll_runs.runs.calculated', ['count' => $run->employee_count]));
    }

    /**
     * Progress of a queued calculation (polled by the run page).
     */
    public function progress(PayrollRun $payrollRun): JsonResponse
    {
        Gate::authorize('view', $payrollRun);
        $payrollRun->refresh();

        return response()->json([
            'status' => $payrollRun->status->value, 'done' => $payrollRun->progress_done, 'total' => $payrollRun->progress_total,
            'percent' => $payrollRun->progressPercent(), 'error' => $payrollRun->calculation_error,
        ]);
    }

    public function submit(Request $request, PayrollRun $payrollRun, ManagePayrollRun $runs): RedirectResponse
    {
        Gate::authorize('submit', $payrollRun);
        $runs->submit($payrollRun, $request->user());

        return back()->with('status', __('payroll_runs.runs.submitted'));
    }

    public function decide(Request $request, PayrollRun $payrollRun, string $decision, ManagePayrollRun $runs): RedirectResponse
    {
        Gate::authorize('decide', $payrollRun);
        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $run = $runs->decide($payrollRun, $request->user(), $decision === 'approve', $data['reason'] ?? null);

        return back()->with('status', match (true) {
            $decision === 'reject' => __('payroll_runs.runs.rejected'),
            $run->status === PayrollRunStatus::Approved => __('payroll_runs.runs.approved'),
            default => __('payroll_runs.runs.step_approved'),
        });
    }

    public function finalise(Request $request, PayrollRun $payrollRun, ManagePayrollRun $runs): RedirectResponse
    {
        Gate::authorize('finalise', $payrollRun);
        $runs->finalise($payrollRun, $request->user());

        return back()->with('status', __('payroll_runs.runs.finalised'));
    }

    public function cancel(Request $request, PayrollRun $payrollRun, ManagePayrollRun $runs): RedirectResponse
    {
        Gate::authorize('cancel', $payrollRun);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $runs->cancel($payrollRun, $request->user(), $data['reason']);

        return back()->with('status', __('payroll_runs.runs.cancelled'));
    }

    public function reverse(Request $request, PayrollRun $payrollRun, ManagePayrollRun $runs): RedirectResponse
    {
        Gate::authorize('reverse', $payrollRun);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $reversal = $runs->reverse($payrollRun, $request->user(), $data['reason']);

        return redirect()->route('admin.payroll-runs.show', $reversal)->with('status', __('payroll_runs.runs.reversed', ['reference' => $payrollRun->reference]));
    }

    public function export(Request $request, PayrollRun $payrollRun, string $kind, PayrollExports $exports, SensitiveDataAccessLog $log): BinaryFileResponse
    {
        Gate::authorize('export', $payrollRun);
        abort_if($kind === 'bank' && ! in_array($payrollRun->status, [PayrollRunStatus::Approved, PayrollRunStatus::Finalised], true), 404);

        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/payroll-'.$kind.'-'.uniqid().'.'.$format);
        $kind === 'bank' ? $exports->bankFile($payrollRun, $path, $format) : $exports->register($payrollRun, $path, $format);
        $log->record(SensitiveDataAccessLog::PAYROLL, $payrollRun, [$kind], ['action' => 'export', 'format' => $format], $request->user());

        return response()->download($path, $payrollRun->reference.'-'.$kind.'.'.$format)->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function scopeOptions(PayrollFormOptions $options): array
    {
        return [
            'branches' => Branch::query()->active()->orderBy('code')->get(),
            'departments' => Department::query()->active()->orderBy('code')->get(),
            'teams' => Team::query()->active()->orderBy('code')->get(),
            'positions' => Position::query()->active()->orderBy('code')->get(),
            'groups' => EmployeeGroup::query()->active()->orderBy('code')->get(),
            'employees' => $options->employees(),
        ];
    }

    /**
     * Branch and department names for the preview rows.
     *
     * @param  array{included: list<array<string, mixed>>, excluded: list<array<string, mixed>>}  $preview
     * @return array{branches: array<int, string>, departments: array<int, string>}
     */
    private function placementNames(array $preview): array
    {
        $rows = [...$preview['included'], ...$preview['excluded']];
        $branchIds = array_filter([...array_column($rows, 'branch_id'), ...array_column($rows, 'from_branch_id')]);
        $departmentIds = array_filter([...array_column($rows, 'department_id'), ...array_column($rows, 'from_department_id')]);

        return [
            'branches' => Branch::query()->withoutGlobalScopes()->whereIn('id', $branchIds)->get()->mapWithKeys(fn (Branch $branch): array => [$branch->id => $branch->name])->all(),
            'departments' => Department::query()->withoutGlobalScopes()->whereIn('id', $departmentIds)->get()->mapWithKeys(fn (Department $department): array => [$department->id => $department->name])->all(),
        ];
    }
}
