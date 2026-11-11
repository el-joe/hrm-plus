<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Payslip;
use App\Services\Employees\PortalProfile;
use App\Services\Payroll\PayslipDocument;
use App\Services\Payroll\PortalPayslips;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * "My payroll" (spec section 5): own payslips (list by year, view, PDF in
 * Arabic or English), the year summary (gross, deductions, net), the loan
 * balance and — when enabled — the current salary structure. Own record only;
 * every view and download is logged.
 */
class PayrollController extends Controller
{
    public function __construct(private readonly PortalProfile $profile, private readonly PortalPayslips $payslips) {}

    public function index(Request $request): View
    {
        $employee = $this->employee($request);
        $years = $this->payslips->years($employee);
        $year = in_array($request->integer('year'), $years, true) ? $request->integer('year') : ($years[0] ?? (int) now()->format('Y'));

        return view('employee.payroll.index', [
            'employee' => $employee,
            'payslips' => $this->payslips->paginate($employee, $year),
            'years' => $years,
            'year' => $year,
            'summary' => $this->payslips->yearSummary($employee, $year),
            'loanBalance' => $this->payslips->loanBalance($employee),
            'structure' => $this->payslips->structure($employee),
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function show(Request $request, int $payslip, PayslipDocument $document): View
    {
        $record = $this->find($request, $payslip);
        $this->payslips->logAccess($record, $request->user(), 'view', 'portal');
        $locale = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : app()->getLocale();

        return view('employee.payroll.show', ['doc' => $document->data($record, $locale), 'payslip' => $record]);
    }

    public function pdf(Request $request, int $payslip, PayslipDocument $document): Response
    {
        $record = $this->find($request, $payslip);
        $this->payslips->logAccess($record, $request->user(), 'pdf', 'portal');
        $locale = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : null;

        return response($document->pdf($record, $locale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$document->fileName($record).'"',
            'X-Content-Type-Options' => 'nosniff',
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
