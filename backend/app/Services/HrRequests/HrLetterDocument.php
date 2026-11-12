<?php

namespace App\Services\HrRequests;

use App\Actions\Company\UpdateCompanyProfile;
use App\Models\Company;
use App\Models\Employee;
use App\Models\HrLetterTemplate;
use App\Models\HrRequest;
use App\Services\Notifications\TemplateRenderer;
use App\Support\Money;
use App\Support\Pdf\ArabicPdf;
use App\Support\SensitiveValue;
use Illuminate\Support\Facades\Storage;

/**
 * Letters issued on HR requests (salary certificate, employment certificate, experience letter):
 * the template body is plain text with {{placeholders}} from PLACEHOLDERS, rendered with the
 * notification TemplateRenderer (no Blade, no expressions) and laid out as an A4 PDF in the letter's
 * language with the shared App\Support\Pdf\ArabicPdf (same set-up as payslips and reports: Cairo
 * font, RTL for Arabic).
 */
class HrLetterDocument
{
    /**
     * @var list<string>
     */
    public const PLACEHOLDERS = [
        'employee_name', 'employee_number', 'job_title', 'department', 'branch', 'hire_date', 'nationality',
        'national_id_masked', 'basic_salary', 'salary_currency', 'company_name', 'today', 'reference', 'addressee',
    ];

    /**
     * Placeholders that reveal pay: issuing such a letter needs employee_financials.view.
     *
     * @var list<string>
     */
    public const FINANCIAL_PLACEHOLDERS = ['basic_salary'];

    public function __construct(private readonly TemplateRenderer $renderer, private readonly ArabicPdf $pdf) {}

    public function usesFinancialData(HrLetterTemplate $template): bool
    {
        $used = [...$this->renderer->placeholdersIn($template->body_ar), ...$this->renderer->placeholdersIn($template->body_en)];

        return array_intersect($used, self::FINANCIAL_PLACEHOLDERS) !== [];
    }

    /**
     * Placeholders of a body that are not offered.
     *
     * @return list<string>
     */
    public function unknownPlaceholders(string $body): array
    {
        return $this->renderer->unknownPlaceholders($body, self::PLACEHOLDERS);
    }

    /**
     * @return array<string, string>
     */
    public function values(Employee $employee, string $locale, ?HrRequest $request = null): array
    {
        $employee->loadMissing(['jobTitle', 'department' => fn ($query) => $query->withoutGlobalScopes(), 'branch' => fn ($query) => $query->withoutGlobalScopes()]);
        $company = Company::current();
        $addressee = $request?->fieldValues->first(fn ($value): bool => $value->field?->key === 'addressee')?->value;

        return [
            'employee_name' => (string) $employee->localized('name', $locale),
            'employee_number' => (string) $employee->employee_number,
            'job_title' => (string) ($employee->jobTitle?->localized('name', $locale) ?? ''),
            'department' => (string) ($employee->department?->localized('name', $locale) ?? ''),
            'branch' => (string) ($employee->branch?->localized('name', $locale) ?? ''),
            'hire_date' => (string) $employee->hire_date?->toDateString(),
            'nationality' => (string) ($employee->nationality ?? ''),
            'national_id_masked' => (string) (SensitiveValue::mask($employee->national_id) ?? ''),
            'basic_salary' => $employee->basic_salary === null ? '' : Money::format((string) $employee->basic_salary),
            'salary_currency' => (string) setting('localisation.currency_code'),
            'company_name' => (string) $company->localized('name', $locale),
            'today' => now()->toDateString(),
            'reference' => (string) ($request?->reference ?? ''),
            'addressee' => (string) ($addressee ?: __('hr_requests.letters.default_addressee', [], $locale)),
        ];
    }

    /**
     * Sample values for the template preview (no real employee data).
     *
     * @return array<string, string>
     */
    public function sampleValues(string $locale): array
    {
        $values = [];

        foreach (self::PLACEHOLDERS as $name) {
            $values[$name] = (string) __('hr_requests.letters.samples.'.$name, [], $locale);
        }

        $values['company_name'] = (string) Company::current()->localized('name', $locale);
        $values['today'] = now()->toDateString();
        $values['salary_currency'] = (string) setting('localisation.currency_code');

        return $values;
    }

    /**
     * @param  array<string, string>  $values
     */
    public function renderBody(HrLetterTemplate $template, string $locale, array $values): string
    {
        return $this->renderer->render($template->body($locale), $values, self::PLACEHOLDERS);
    }

    /**
     * The PDF bytes of a letter for the request's employee.
     */
    public function pdf(HrLetterTemplate $template, HrRequest $request, string $locale): string
    {
        $locale = in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
        $employee = $request->employee()->withoutGlobalScopes()->firstOrFail();
        $request->loadMissing('fieldValues.field');
        $company = Company::current();
        $body = $this->renderBody($template, $locale, $this->values($employee, $locale, $request));

        $data = [
            'locale' => $locale,
            'dir' => $locale === 'ar' ? 'rtl' : 'ltr',
            'title' => $template->localized('name', $locale),
            'paragraphs' => array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $body) ?: []))),
            'reference' => $request->reference,
            'date' => now()->toDateString(),
            'company' => ['name' => $company->localized('name', $locale), 'address' => $company->localized('address', $locale), 'tax_number' => $company->tax_number],
            'logo_path' => $company->logo_path !== null && Storage::disk(UpdateCompanyProfile::LOGO_DISK)->exists($company->logo_path)
                ? Storage::disk(UpdateCompanyProfile::LOGO_DISK)->path($company->logo_path) : null,
        ];

        return $this->pdf->render(view('hr-requests.letter-pdf', $data)->render(), $data['dir'], [
            'title' => $data['title'].' '.$data['reference'],
            'author' => $data['company']['name'] ?? config('app.name'),
            'margins' => ['top' => 18, 'bottom' => 18, 'left' => 20, 'right' => 20],
        ]);
    }

    public function fileName(HrLetterTemplate $template, HrRequest $request, string $locale): string
    {
        return $template->code.'-'.$request->reference.'-'.$locale.'.pdf';
    }
}
