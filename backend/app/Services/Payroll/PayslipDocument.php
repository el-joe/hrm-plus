<?php

namespace App\Services\Payroll;

use App\Actions\Company\UpdateCompanyProfile;
use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayrollLineSource;
use App\Models\Company;
use App\Models\PayrollItemLine;
use App\Models\Payslip;
use App\Services\Employees\EmployeeFileStore;
use Illuminate\Support\Facades\Storage;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\MpdfException;

/**
 * Builds a payslip (spec module 9 G) from its frozen payroll item: employee
 * information, period, basic salary, allowances / bonuses / overtime,
 * deductions, loans and advances, taxes and insurance, gross, net and the
 * payment status — in the employee's language (payslips.locale) unless
 * another one is asked for.
 *
 * pdf(): an A4 PDF rendered by mPDF with the Cairo font (OTL shaping for
 * Arabic, RTL layout for "ar"), company logo when one is set. The file is
 * cached on the private employee files disk under "payslips/"; content never
 * changes because the item is frozen, so the cache is only an optimisation.
 */
class PayslipDocument
{
    /** Bump when the PDF template changes so cached files are rebuilt. */
    public const TEMPLATE_VERSION = 4;

    public function __construct(private readonly EmployeeFileStore $files) {}

    /**
     * @return array<string, mixed>
     */
    public function data(Payslip $payslip, ?string $locale = null): array
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : ($payslip->locale === 'en' ? 'en' : 'ar');
        $payslip->loadMissing([
            'period', 'run',
            'employee' => fn ($query) => $query->withoutGlobalScopes()->with(['department', 'jobTitle', 'branch', 'position']),
            'item.lines', 'payments',
        ]);
        $lines = $payslip->item->lines->filter(fn (PayrollItemLine $line): bool => $line->show_on_payslip && bccomp((string) $line->amount, '0', 2) !== 0);
        $row = fn (PayrollItemLine $line): array => [
            'name' => $line->name($locale), 'code' => $line->code, 'amount' => (string) $line->amount, 'quantity' => $line->quantity === null || ! in_array($line->source, [PayrollLineSource::Attendance, PayrollLineSource::Leave, PayrollLineSource::Encashment], true)
                ? null : PayrollMath::trim((string) $line->quantity),
            'note' => $line->explain($locale),
        ];
        $deductions = $lines->where('kind', PayComponentKind::Deduction);
        $company = Company::current();
        $employee = $payslip->employee;
        $demo = collect($payslip->item->rule_versions['statutory'] ?? [])->contains(fn (array $rule): bool => (bool) ($rule['is_demo'] ?? false));

        return [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'company' => ['name' => $company->localized('name', $locale), 'legal_name' => $company->localized('legal_name', $locale), 'address' => $company->localized('address', $locale), 'tax_number' => $company->tax_number],
            'logo_path' => $company->logo_path !== null && Storage::disk(UpdateCompanyProfile::LOGO_DISK)->exists($company->logo_path)
                ? Storage::disk(UpdateCompanyProfile::LOGO_DISK)->path($company->logo_path) : null,
            'logo_url' => $company->logoUrl(),
            'payslip' => $payslip,
            'employee' => [
                'name' => $employee->localized('name', $locale), 'number' => $employee->employee_number,
                'department' => $employee->department?->localized('name', $locale), 'branch' => $employee->branch?->localized('name', $locale),
                'job_title' => $employee->jobTitle?->localized('name', $locale), 'hire_date' => $employee->hire_date?->toDateString(),
            ],
            'period' => ['name' => $payslip->period->localized('name', $locale), 'from' => $payslip->period->start_date->toDateString(), 'to' => $payslip->period->end_date->toDateString(), 'pay_date' => $payslip->period->pay_date->toDateString()],
            'run' => ['reference' => $payslip->run->reference, 'type' => $payslip->run->type->value],
            'basic_salary' => $payslip->item->basic_salary === null ? null : (string) $payslip->item->basic_salary,
            'earnings' => $lines->where('kind', PayComponentKind::Earning)->map($row)->values()->all(),
            'deductions' => $deductions->reject(fn (PayrollItemLine $line): bool => $this->isLoan($line) || $this->isStatutory($line))->map($row)->values()->all(),
            'loans' => $deductions->filter(fn (PayrollItemLine $line): bool => $this->isLoan($line))->map($row)->values()->all(),
            'statutory' => $deductions->filter(fn (PayrollItemLine $line): bool => $this->isStatutory($line))->map($row)->values()->all(),
            'employer' => $lines->where('kind', PayComponentKind::EmployerContribution)->map($row)->values()->all(),
            'gross' => (string) $payslip->gross_pay,
            'deductions_total' => (string) $payslip->total_deductions,
            'net' => (string) $payslip->net_pay,
            'currency' => $payslip->currency,
            'demo' => $demo,
            'void' => $payslip->isVoid(),
        ];
    }

    /**
     * The PDF bytes (cached on the private disk).
     */
    public function pdf(Payslip $payslip, ?string $locale = null): string
    {
        $data = $this->data($payslip, $locale);
        $path = 'payslips/'.$payslip->employee_id.'/'.$payslip->number.'-'.$data['locale'].($data['void'] ? '-void' : '').'-'.substr(hash('sha256', self::TEMPLATE_VERSION.'|'.$payslip->issued_at->getTimestamp().'|'.$payslip->item->calculation_hash.'|'.$payslip->payment_status->value.'|'.$payslip->amount_paid.'|'.$payslip->status->value), 0, 16).'.pdf';
        $disk = $this->files->disk();

        if ($disk->exists($path)) {
            return (string) $disk->get($path);
        }

        // Arabic short vowels (harakat) are optional in print and the Cairo font's mark
        // filtering sets are not supported by mPDF, so they are removed from the PDF only.
        $html = (string) preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', view('payslips.pdf', $data)->render());
        $tempDir = storage_path('framework/mpdf');

        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }

        try {
            $bytes = $this->render($html, $data, 'cairo', $tempDir);
        } catch (MpdfException) {
            // A glyph sequence Cairo cannot shape in mPDF: fall back to mPDF's bundled Arabic font.
            $bytes = $this->render(str_replace('font-family: cairo', 'font-family: xbriyaz', $html), $data, 'xbriyaz', $tempDir);
        }

        $disk->put($path, $bytes);
        $payslip->forceFill(['pdf_path' => $path])->saveQuietly();

        return $bytes;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function render(string $html, array $data, string $font, string $tempDir): string
    {
        $fontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $fontData = (new FontVariables)->getDefaults()['fontdata'];
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'fontDir' => [...$fontDirs, resource_path('fonts/cairo')],
            'fontdata' => $fontData + ['cairo' => ['R' => 'Cairo-400.ttf', 'B' => 'Cairo-700.ttf', 'useOTL' => 0xFF, 'useKashida' => 75]],
            'default_font' => $font,
            'directionality' => $data['dir'],
            'margin_top' => 14, 'margin_bottom' => 16, 'margin_left' => 14, 'margin_right' => 14,
        ]);
        $mpdf->SetTitle($data['payslip']->number);
        $mpdf->SetAuthor($data['company']['name'] ?? config('app.name'));
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    public function fileName(Payslip $payslip): string
    {
        return $payslip->number.'.pdf';
    }

    private function isLoan(PayrollItemLine $line): bool
    {
        return in_array($line->category, [PayComponentCategory::Loan, PayComponentCategory::Advance], true);
    }

    private function isStatutory(PayrollItemLine $line): bool
    {
        return in_array($line->category, [PayComponentCategory::Tax, PayComponentCategory::Insurance], true);
    }
}
