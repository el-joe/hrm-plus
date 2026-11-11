<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PayslipResource;
use App\Models\Employee;
use App\Models\Payslip;
use App\Services\Employees\PortalProfile;
use App\Services\Payroll\PayslipDocument;
use App\Services\Payroll\PortalPayslips;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /api/v1/me/payslips?year=&page=   own issued payslips, newest first (paginated, 12 per page)
 * GET /api/v1/me/payslips/summary?year= year summary (count, gross, deductions, net, paid), years, loan balance,
 *                                       current salary structure (when payroll.portal_show_structure)
 * GET /api/v1/me/payslips/{id}          one payslip with its lines (earnings, deductions, loans, statutory)
 * GET /api/v1/me/payslips/{id}/pdf?lang=ar|en  the PDF (application/pdf)
 *
 * Always the token owner's own payslips; every view and download is logged.
 */
class PayslipController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalPayslips $payslips) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);

        return ApiResponse::paginated($this->payslips->paginate($this->employee($request), $data['year'] ?? null), PayslipResource::class);
    }

    public function summary(Request $request): JsonResponse
    {
        $employee = $this->employee($request);
        $years = $this->payslips->years($employee);
        $year = (int) ($request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']])['year'] ?? ($years[0] ?? now()->format('Y')));

        return ApiResponse::success([
            ...$this->payslips->yearSummary($employee, $year),
            'years' => $years,
            'currency' => (string) setting('localisation.currency_code'),
            'loan_balance' => $this->payslips->loanBalance($employee),
            'structure' => $this->payslips->structure($employee),
        ]);
    }

    public function show(Request $request, int $payslip, PayslipDocument $document): JsonResponse
    {
        $record = $this->find($request, $payslip);
        $this->payslips->logAccess($record, $request->user(), 'view', 'api');

        return ApiResponse::success((new PayslipResource($record))->withDocument($document->data($record, app()->getLocale())));
    }

    public function pdf(Request $request, int $payslip, PayslipDocument $document): Response
    {
        $record = $this->find($request, $payslip);
        $this->payslips->logAccess($record, $request->user(), 'pdf', 'api');
        $locale = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : null;

        return response($document->pdf($record, $locale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$document->fileName($record).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function find(Request $request, int $id): Payslip
    {
        $record = $this->payslips->find($this->employee($request), $id);
        abort_if($record === null, 404);

        return $record;
    }

    private function employee(Request $request): Employee
    {
        $employee = $this->profile->employeeFor($request->user());
        abort_if($employee === null, 404);

        return $employee;
    }
}
