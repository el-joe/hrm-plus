<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Actions\Payroll\ManagePayslipPayments;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Payroll\PayslipPaymentRequest;
use App\Models\PayrollPeriod;
use App\Models\Payslip;
use App\Services\Employees\SensitiveDataAccessLog;
use App\Services\Employees\Spreadsheet;
use App\Services\Payroll\PayslipDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payslips in the admin area (payslips.view inside the data scope): list
 * with payment status, the payslip page (HTML), the Arabic / English PDF,
 * payments and bulk "mark paid" (payslips.manage), payslip list export.
 * Every view and download is logged (sensitive_access.payslip).
 */
class PayslipController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Payslip::class);
        $filters = $this->filters($request);

        return view('admin.payroll.payslips.index', [
            'payslips' => $this->query($filters)->with(['employee', 'period', 'run'])->latest('id')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'periods' => PayrollPeriod::query()->orderByDesc('start_date')->get(),
            'statuses' => PaymentStatus::cases(),
            'methods' => PaymentMethod::cases(),
            'canPay' => $request->user()->can('pay', Payslip::class),
            'outstanding' => bcadd((string) $this->query($filters)->where('status', 'issued')->selectRaw('coalesce(sum(net_pay - amount_paid), 0) as rest')->value('rest'), '0', 2),
        ]);
    }

    public function show(Request $request, Payslip $payslip, PayslipDocument $document, SensitiveDataAccessLog $log): View
    {
        Gate::authorize('view', $payslip);
        $log->record(SensitiveDataAccessLog::PAYSLIP, $payslip, ['net_pay', 'lines'], ['action' => 'view', 'channel' => 'admin', 'employee_id' => $payslip->employee_id], $request->user());
        $locale = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : app()->getLocale();

        return view('admin.payroll.payslips.show', [
            'doc' => $document->data($payslip, $locale),
            'payslip' => $payslip->load(['payments.recorder']),
            'methods' => PaymentMethod::cases(),
            'canPay' => $request->user()->can('pay', $payslip),
        ]);
    }

    public function pdf(Request $request, Payslip $payslip, PayslipDocument $document, SensitiveDataAccessLog $log): Response
    {
        Gate::authorize('view', $payslip);
        $locale = in_array($request->query('lang'), ['ar', 'en'], true) ? $request->query('lang') : null;
        $log->record(SensitiveDataAccessLog::PAYSLIP, $payslip, ['net_pay', 'lines'], ['action' => 'pdf', 'channel' => 'admin', 'employee_id' => $payslip->employee_id], $request->user());

        return response($document->pdf($payslip, $locale), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline').'; filename="'.$document->fileName($payslip).'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function pay(PayslipPaymentRequest $request, Payslip $payslip, ManagePayslipPayments $payments): RedirectResponse
    {
        $payments->record($payslip, $request->validated(), $request->user());

        return back()->with('status', __('payroll_runs.payslips.payment_recorded'));
    }

    public function markPaid(PayslipPaymentRequest $request, ManagePayslipPayments $payments): RedirectResponse
    {
        $payslips = Payslip::query()->whereIn('id', $request->validated('payslip_ids'))->get()->filter(fn (Payslip $payslip): bool => $request->user()->can('pay', $payslip));
        $count = $payments->markPaid($payslips, $request->validated(), $request->user());

        return back()->with('status', __('payroll_runs.payslips.marked_paid', ['count' => $count]));
    }

    public function export(Request $request, Spreadsheet $spreadsheet, SensitiveDataAccessLog $log): BinaryFileResponse
    {
        Gate::authorize('export', Payslip::class);
        $filters = $this->filters($request);
        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $path = storage_path('app/private/payslips-'.uniqid().'.'.$format);
        $headers = array_map(fn (string $key): string => __('payroll_runs.payslips.fields.'.$key), ['number', 'employee', 'period', 'gross', 'deductions', 'net', 'payment_status', 'amount_paid', 'paid_on', 'reference']);

        $rows = $this->query($filters)->with(['employee', 'period'])->orderBy('id')->lazy()->map(fn (Payslip $payslip): array => [
            $payslip->number, $payslip->employee->label(), $payslip->period->name, (string) $payslip->gross_pay, (string) $payslip->total_deductions, (string) $payslip->net_pay,
            $payslip->isVoid() ? $payslip->status->label() : $payslip->payment_status->label(), (string) $payslip->amount_paid, $payslip->paid_on?->toDateString() ?? '', $payslip->payment_reference ?? '',
        ]);
        $spreadsheet->write($path, $format, $headers, $rows);
        $log->record(SensitiveDataAccessLog::PAYSLIP, null, ['net_pay'], ['action' => 'export', 'filters' => $filters], $request->user());

        return response()->download($path, 'payslips-'.now()->format('Ymd').'.'.$format)->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'period' => ['nullable', 'integer'],
            'run' => ['nullable', 'integer'],
            'payment_status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Payslip>
     */
    private function query(array $filters): Builder
    {
        return Payslip::query()
            ->when($filters['period'] ?? null, fn (Builder $query, int $id) => $query->where('payroll_period_id', $id))
            ->when($filters['run'] ?? null, fn (Builder $query, int $id) => $query->where('payroll_run_id', $id))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status)->where('status', 'issued'))
            ->when($filters['search'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $query) => $query->whereLike('number', "%{$term}%")->orWhereHas('employee', fn (Builder $employee) => $employee->search($term))));
    }
}
