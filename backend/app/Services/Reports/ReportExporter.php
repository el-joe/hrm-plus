<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Models\User;
use App\Services\Employees\Spreadsheet;
use App\Support\Formatter;
use App\Support\Pdf\ArabicPdf;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Writes a report to a file: Excel (XLSX) and CSV through openspout
 * (App\Services\Employees\Spreadsheet, streamed) and an Arabic / English PDF through mPDF with
 * the Cairo font (App\Support\Pdf\ArabicPdf, the payslip setup). Only the columns the admin may
 * see are written; a totals row closes every file that has totals.
 *
 * Cell values: dates in the configured date format; numbers as numbers in XLSX, exact DECIMAL
 * strings in CSV (money is never rounded through float in CSV); the PDF uses the full display
 * formatting (grouping, Arabic-Indic digits when that setting is on).
 */
class ReportExporter
{
    public const FORMATS = ['xlsx', 'csv', 'pdf'];

    /**
     * Rows above which an export is queued instead of downloaded (config/reports.php).
     */
    public static function queueThreshold(string $format): int
    {
        return (int) config('reports.queue_threshold.'.$format, 5000);
    }

    /**
     * Largest PDF export (config/reports.php); bigger ones must use Excel / CSV.
     */
    public static function pdfMaxRows(): int
    {
        return (int) config('reports.pdf_max_rows', 3000);
    }

    public function __construct(
        private readonly ReportRunner $runner,
        private readonly ReportFilters $filters,
        private readonly Spreadsheet $spreadsheet,
        private readonly ArabicPdf $pdf,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return int rows written
     */
    public function write(Report $report, array $filters, User $user, string $format, string $path): int
    {
        $columns = $this->runner->columns($report, $user);
        $count = 0;
        $totalsRows = [];

        if ($format === 'pdf') {
            $rows = [];

            foreach ($this->runner->stream($report, $filters, $user) as $row) {
                $rows[] = $row;
                $count++;
            }

            file_put_contents($path, $this->pdf($report, $filters, $columns, $rows));
            $this->runner->logSensitive($report, $columns, $user, 'export', $filters, $format);

            return $count;
        }

        $generator = (function () use ($report, $filters, $user, $columns, $format, &$count, &$totalsRows): \Generator {
            foreach ($this->runner->stream($report, $filters, $user) as $row) {
                $count++;
                $totalsRows[] = $this->totalsSlice($row, $columns);

                yield $this->cells($row, $columns, $format);
            }

            $totals = $this->runner->totals($totalsRows, $columns);

            if ($totals !== [] && $count > 0) {
                yield $this->totalsRow($columns, $totals, $format);
            }
        })();

        $this->spreadsheet->write($path, $format, array_values(array_map(fn (Column $column): string => $column->label, $columns)), $generator);
        $this->runner->logSensitive($report, $columns, $user, 'export', $filters, $format);

        return $count;
    }

    public function fileName(Report $report, array $filters, string $format): string
    {
        $period = isset($filters['from'], $filters['to']) ? '-'.$filters['from'].'-'.$filters['to'] : '';

        return 'report-'.str_replace('_', '-', $report->key()).$period.'.'.$format;
    }

    /**
     * Display value of a cell (screen and PDF).
     */
    public static function display(Column $column, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($column->type) {
            'money' => Formatter::money((string) $value),
            'number' => Formatter::number(is_numeric($value) ? (int) $value : $value),
            'decimal' => Formatter::number((string) $value, $column->decimals),
            'percent' => Formatter::percent($value, $column->decimals),
            'date' => Formatter::date($value),
            'datetime' => Formatter::dateTime($value),
            'duration', 'code' => Formatter::digits((string) $value),
            default => $value instanceof DateTimeInterface ? Formatter::date($value) : (string) $value,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, Column>  $columns
     * @return list<mixed>
     */
    private function cells(array $row, array $columns, string $format): array
    {
        $cells = [];

        foreach ($columns as $key => $column) {
            $value = $row[$key] ?? null;
            $cells[] = match (true) {
                $value === null => '',
                $column->type === 'date' => Formatter::plainDate($value),
                $column->type === 'datetime' => $value instanceof DateTimeInterface || is_string($value)
                    ? CarbonImmutable::parse($value)->setTimezone(Formatter::timezone())->format(Formatter::dateFormat().' '.Formatter::timeFormat()) : (string) $value,
                $column->type === 'number' && is_numeric($value) => (int) $value,
                in_array($column->type, ['money', 'decimal', 'percent'], true) && is_numeric($value) => $format === 'xlsx' ? (float) $value : (string) $value,
                default => $value instanceof DateTimeInterface ? Formatter::plainDate($value) : (string) $value,
            };
        }

        return $cells;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, Column>  $columns
     * @return array<string, mixed>
     */
    private function totalsSlice(array $row, array $columns): array
    {
        return array_intersect_key($row, array_filter($columns, fn (Column $column): bool => $column->total && $column->isNumeric()));
    }

    /**
     * @param  array<string, Column>  $columns
     * @param  array<string, string>  $totals
     * @return list<mixed>
     */
    private function totalsRow(array $columns, array $totals, string $format): array
    {
        $cells = [];
        $first = true;

        foreach ($columns as $key => $column) {
            if (isset($totals[$key])) {
                $cells[] = $column->type === 'number' ? (int) $totals[$key] : ($format === 'xlsx' ? (float) $totals[$key] : $totals[$key]);
            } else {
                $cells[] = $first ? __('reports.ui.total') : '';
            }

            $first = false;
        }

        return $cells;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, Column>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function pdf(Report $report, array $filters, array $columns, array $rows): string
    {
        $locale = app()->getLocale();
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';
        $company = Company::current();
        $totals = $this->runner->totals($rows, $columns);

        $html = view('admin.reports.pdf', [
            'report' => $report,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
            'filters' => $this->filters->describe($report, $filters),
            'dir' => $dir,
            'locale' => $locale,
            'company' => $company?->name,
            'generatedAt' => Formatter::dateTime(now()),
        ])->render();

        return $this->pdf->render($html, $dir, [
            'title' => $report->title(),
            'author' => $company?->name ?? config('app.name'),
            'orientation' => count($columns) > 6 ? 'L' : 'P',
            'footer' => '<div style="font-family: cairo; font-size: 8pt; color: #6b7280; text-align: center;">'.e($report->title()).' · {PAGENO} / {nbpg}</div>',
        ]);
    }
}
