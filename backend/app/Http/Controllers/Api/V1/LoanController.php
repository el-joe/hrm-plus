<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Payroll\ManageLoan;
use App\Enums\LoanSource;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\OwnLoanRequest;
use App\Http\Resources\V1\EmployeeLoanResource;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use App\Services\Employees\PortalProfile;
use App\Services\Payroll\LoanEligibility;
use App\Services\Payroll\PortalLoans;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GET  /api/v1/me/loans?status=&page=     own loans and advances (paginated)
 * GET  /api/v1/me/loans/limits            limits per type (max amount, instalments, instalment)
 * POST /api/v1/me/loans                   request {type, amount, instalments, start_month (YYYY-MM), reason}
 * GET  /api/v1/me/loans/{id}              detail with schedule, ledger and approval history
 * POST /api/v1/me/loans/{id}/cancel       withdraw a pending request ({reason})
 *
 * Always the token owner's own employee record.
 */
class LoanController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalLoans $loans) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::enum(LoanStatus::class)]]);
        $query = $this->loans->loans($this->employee($request))->when($data['status'] ?? null, fn ($query, string $status) => $query->where('status', $status));

        return ApiResponse::paginated($query->paginate(20), EmployeeLoanResource::class);
    }

    public function limits(Request $request, LoanEligibility $eligibility): JsonResponse
    {
        $employee = $this->employee($request);

        return ApiResponse::success(collect(LoanType::cases())->mapWithKeys(fn (LoanType $type): array => [$type->value => [
            ...$eligibility->limits($employee, $type),
            'label' => $type->label(),
        ]])->all(), meta: [
            'requests_enabled' => (bool) setting('loans.employee_requests_enabled'),
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function store(OwnLoanRequest $request, ManageLoan $manage): JsonResponse
    {
        $loan = $manage->request($this->employee($request), $request->loanData(), LoanSource::Api, $request->user());

        return ApiResponse::success(new EmployeeLoanResource($this->find($request, $loan->id)), __('payroll.loans.portal.sent'), 201);
    }

    public function show(Request $request, int $loan): JsonResponse
    {
        return ApiResponse::success(new EmployeeLoanResource($this->find($request, $loan)));
    }

    public function cancel(Request $request, int $loan, ManageLoan $manage): JsonResponse
    {
        $record = $this->find($request, $loan);
        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:1000']])['reason'] ?? null;
        $manage->cancel($record, $request->user(), $reason);

        return ApiResponse::success(new EmployeeLoanResource($this->find($request, $loan)), __('payroll.loans.portal.cancelled'));
    }

    private function find(Request $request, int $id): EmployeeLoan
    {
        $loan = $this->loans->find($this->employee($request), $id);
        abort_if($loan === null, 404);

        return $loan;
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
