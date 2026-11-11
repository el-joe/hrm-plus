<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Actions\Payroll\ManageLoan;
use App\Enums\LoanChangeType;
use App\Enums\LoanSource;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payroll\StoreLoanRequest;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Models\LoanChange;
use App\Services\Employees\Spreadsheet;
use App\Services\Payroll\LoanEligibility;
use App\Services\Payroll\PayrollFormOptions;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Loans and advances: the request queue (inside the data scope), requests
 * on an employee's behalf, the loan page (schedule, ledger, approval
 * history), decisions with approved terms, early repayment, skip /
 * reschedule requests and their decisions, and final settlement on
 * offboarding. Rules live in App\Actions\Payroll\ManageLoan.
 */
class LoanController extends Controller
{
    public const PER_PAGE = 25;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', EmployeeLoan::class);

        $filters = $this->filters($request);

        return view('admin.payroll.loans.index', [
            'loans' => $this->query($filters)->with(['employee', 'latestApprovalRequest.currentStep'])->latest('id')->paginate(self::PER_PAGE)->withQueryString(),
            'filters' => $filters,
            'statuses' => LoanStatus::cases(),
            'types' => LoanType::cases(),
            'counts' => [
                'pending' => EmployeeLoan::query()->where('status', LoanStatus::Pending)->count(),
                'active' => EmployeeLoan::query()->where('status', LoanStatus::Active)->count(),
                'outstanding' => bcadd((string) EmployeeLoan::query()->where('status', LoanStatus::Active)->sum('outstanding_amount'), '0', 2),
            ],
        ]);
    }

    public function create(Request $request, PayrollFormOptions $options, LoanEligibility $eligibility): View
    {
        Gate::authorize('create', EmployeeLoan::class);

        $employee = $request->integer('employee') ? Employee::query()->find($request->integer('employee')) : null;

        return view('admin.payroll.loans.create', [
            'employees' => $options->employees()->reject(fn (Employee $row): bool => $row->id === $request->user()->employee_id),
            'employee' => $employee,
            'types' => LoanType::cases(),
            'limits' => $employee ? collect(LoanType::cases())->mapWithKeys(fn (LoanType $type): array => [$type->value => $eligibility->limits($employee, $type)])->all() : null,
            'startMonth' => now()->addMonthNoOverflow()->format('Y-m'),
        ]);
    }

    public function store(StoreLoanRequest $request, ManageLoan $loans): RedirectResponse
    {
        $loan = $loans->request($request->employee(), $request->loanData(), LoanSource::Admin, $request->user());

        return redirect()->route('admin.loans.show', $loan)->with('status', __('payroll.loans.submitted', ['reference' => $loan->reference]));
    }

    public function show(Request $request, EmployeeLoan $loan, LoanEligibility $eligibility): View
    {
        Gate::authorize('view', $loan);

        $loan->load([
            'employee', 'requester', 'decider', 'instalments', 'transactions.creator',
            'changes' => fn ($query) => $query->with(['instalment', 'requester', 'latestApprovalRequest.currentStep']),
            'approvalRequests' => fn ($query) => $query->with(['actions.actor', 'currentStep', 'workflow'])->latest('id'),
        ]);

        return view('admin.payroll.loans.show', [
            'loan' => $loan,
            'limits' => $eligibility->limits($loan->employee, $loan->type),
            'changeTypes' => LoanChangeType::cases(),
            'scheduled' => $loan->instalments->filter->isScheduled()->values(),
            'canDecide' => $request->user()->can('decide', $loan),
            'canUpdate' => $request->user()->can('update', $loan),
            'lastWorkingDay' => $loan->employee->offboardingCases()->first()?->last_working_date?->toDateString(),
        ]);
    }

    public function decide(Request $request, EmployeeLoan $loan, string $decision, ManageLoan $loans): RedirectResponse
    {
        Gate::authorize('decide', $loan);

        $data = $request->validate([
            'reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000'],
            'approved_amount' => ['nullable', 'decimal:0,2', 'gt:0'],
            'instalment_count' => ['nullable', 'integer', 'between:1,120'],
            'start_month' => ['nullable', 'date_format:Y-m'],
        ]);

        $loans->decide($loan, $request->user(), $decision === 'approve', $data['reason'] ?? null, $data);

        return back()->with('status', __($decision === 'approve' ? 'payroll.loans.approved_step' : 'payroll.loans.rejected', ['reference' => $loan->reference]));
    }

    public function cancel(Request $request, EmployeeLoan $loan, ManageLoan $loans): RedirectResponse
    {
        Gate::authorize('cancel', $loan);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $loans->cancel($loan, $request->user(), $data['reason']);

        return back()->with('status', __('payroll.loans.cancelled', ['reference' => $loan->reference]));
    }

    public function repay(Request $request, EmployeeLoan $loan, ManageLoan $loans): RedirectResponse
    {
        Gate::authorize('update', $loan);

        $data = $request->validate([
            'amount' => ['required', 'decimal:0,2', 'gt:0'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $loans->repay($loan, (string) $data['amount'], $data['paid_on'], $data['note'] ?? null, $request->user());

        return back()->with('status', __('payroll.loans.repaid', ['amount' => Money::format((string) $data['amount'])]));
    }

    public function storeChange(Request $request, EmployeeLoan $loan, ManageLoan $loans): RedirectResponse
    {
        Gate::authorize('update', $loan);

        $data = $request->validate([
            'type' => ['required', Rule::enum(LoanChangeType::class)],
            'instalment_id' => ['nullable', 'integer'],
            'new_instalment_count' => ['nullable', 'integer', 'between:1,120'],
            'new_start_month' => ['nullable', 'date_format:Y-m'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $loans->requestChange($loan, LoanChangeType::from($data['type']), $data, $request->user());

        return back()->with('status', __('payroll.loans.change_requested'));
    }

    public function decideChange(Request $request, EmployeeLoan $loan, LoanChange $change, string $decision, ManageLoan $loans): RedirectResponse
    {
        abort_unless($change->employee_loan_id === $loan->id, 404);
        Gate::authorize('view', $loan);
        Gate::authorize('decide', $change);

        $data = $request->validate(['reason' => [$decision === 'reject' ? 'required' : 'nullable', 'string', 'max:1000']]);
        $loans->decideChange($change, $request->user(), $decision === 'approve', $data['reason'] ?? null);

        return back()->with('status', __($decision === 'approve' ? 'payroll.loans.change_approved' : 'payroll.loans.change_rejected'));
    }

    public function settle(Request $request, EmployeeLoan $loan, ManageLoan $loans): RedirectResponse
    {
        Gate::authorize('update', $loan);

        $data = $request->validate(['last_working_day' => ['required', 'date']]);
        $loans->settleOnFinalPay($loan, $data['last_working_day'], $request->user());

        return back()->with('status', __('payroll.loans.settlement_scheduled', ['date' => $data['last_working_day']]));
    }

    public function export(Request $request, Spreadsheet $spreadsheet): BinaryFileResponse
    {
        Gate::authorize('export', EmployeeLoan::class);

        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/loans-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('payroll.loans.fields.'.$key), [
            'reference', 'employee', 'type', 'status', 'requested_amount', 'approved_amount', 'instalments', 'start_month', 'outstanding',
        ]);

        $rows = $this->query($filters)->with('employee')->orderBy('id')->lazy()->map(fn (EmployeeLoan $loan): array => [
            $loan->reference, $loan->employee->label(), $loan->type->label(), $loan->status->label(), Money::format((string) $loan->requested_amount),
            $loan->approved_amount === null ? '' : Money::format((string) $loan->approved_amount), $loan->instalment_count ?? $loan->requested_instalments,
            ($loan->start_date ?? $loan->requested_start)->format('Y-m'), Money::format((string) $loan->outstanding_amount),
        ]);

        $spreadsheet->write($path, $format, $headers, $rows);

        return response()->download($path, 'loans-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(LoanStatus::class)],
            'type' => ['nullable', Rule::enum(LoanType::class)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<EmployeeLoan>
     */
    private function query(array $filters): Builder
    {
        return EmployeeLoan::query()
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query
                ->whereLike('reference', "%{$term}%")->orWhereHas('employee', fn (Builder $employee) => $employee->search($term))));
    }
}
