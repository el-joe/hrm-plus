<?php

namespace App\Http\Controllers\Employee;

use App\Actions\Payroll\ManageLoan;
use App\Enums\LoanSource;
use App\Enums\LoanType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\OwnLoanRequest;
use App\Models\Employee;
use App\Services\Employees\PortalProfile;
use App\Services\Payroll\LoanEligibility;
use App\Services\Payroll\PortalLoans;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My loans" (spec section 5 "My Payroll": loan balance, advance history):
 * limits, new request, history and the schedule of each loan. Own record only.
 * Payslips arrive with Phase 7b.
 */
class LoanController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalLoans $loans) {}

    public function index(Request $request, LoanEligibility $eligibility): View
    {
        $employee = $this->employee($request);

        return view('employee.loans.index', [
            'employee' => $employee,
            'loans' => $this->loans->paginate($employee, 15),
            'types' => LoanType::cases(),
            'limits' => collect(LoanType::cases())->mapWithKeys(fn (LoanType $type): array => [$type->value => $eligibility->limits($employee, $type)])->all(),
            'requestsEnabled' => (bool) setting('loans.employee_requests_enabled'),
            'startMonth' => now()->addMonthNoOverflow()->format('Y-m'),
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function store(OwnLoanRequest $request, ManageLoan $manage): RedirectResponse
    {
        $loan = $manage->request($this->employee($request), $request->loanData(), LoanSource::Portal, $request->user());

        return redirect()->route('portal.loans.show', $loan->id)->with('status', __('payroll.loans.portal.sent'));
    }

    public function show(Request $request, int $loan): View
    {
        $record = $this->loans->find($this->employee($request), $loan);
        abort_if($record === null, 404);

        return view('employee.loans.show', ['loan' => $record, 'currency' => (string) setting('localisation.currency_code')]);
    }

    public function cancel(Request $request, int $loan, ManageLoan $manage): RedirectResponse
    {
        $record = $this->loans->find($this->employee($request), $loan);
        abort_if($record === null, 404);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null;
        $manage->cancel($record, $request->user(), $reason);

        return redirect()->route('portal.loans')->with('status', __('payroll.loans.portal.cancelled'));
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
