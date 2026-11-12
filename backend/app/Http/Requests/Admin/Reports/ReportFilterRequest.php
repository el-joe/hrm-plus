<?php

namespace App\Http\Requests\Admin\Reports;

use App\Services\Reports\Report;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of one report of the reports centre (shared filters + the report's own), for the screen
 * and the exports. The {report} route parameter must be a registered report the admin may open;
 * exports also need reports.export.
 */
class ReportFilterRequest extends FormRequest
{
    private ?Report $resolved = null;

    public function authorize(): bool
    {
        $report = $this->report();

        if ($report === null || ! $report->isAvailableTo($this->user())) {
            return false;
        }

        return ! $this->routeIs('admin.reports.export') || $this->user()->can('reports.export');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = app(ReportFilters::class)->rules($this->report());

        if ($this->routeIs('admin.reports.export')) {
            $rules['format'] = ['required', Rule::in(ReportExporter::FORMATS)];
        }

        return $rules;
    }

    public function report(): ?Report
    {
        return $this->resolved ??= app(ReportRegistry::class)->find((string) $this->route('report'));
    }

    /**
     * Validated filters with the report's defaults.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $validated = $this->validated();
        unset($validated['format']);

        return app(ReportFilters::class)->resolve($this->report(), $validated);
    }

    /**
     * A 404 for unknown reports rather than a 403.
     */
    protected function prepareForValidation(): void
    {
        abort_if($this->report() === null, 404);
    }
}
