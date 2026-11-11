<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Actions\Payroll\SaveStatutoryRuleVersion;
use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Enums\StatutoryType;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Payroll\StatutoryRuleSetRequest;
use App\Http\Requests\Admin\Payroll\StatutoryVersionRequest;
use App\Models\StatutoryRuleBracket;
use App\Models\StatutoryRuleSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Statutory rule tables (income tax, social insurance) by country, with
 * effective-dated versions. All rates are data entered here; demo tables
 * are labelled "DEMO — not legal advice" everywhere they appear.
 */
class StatutoryRuleController extends StructureController
{
    public const EXTRA_BRACKETS = 3;

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StatutoryRuleSet::class);

        $filters = $this->filters($request);
        $records = $this->applyFilters(StatutoryRuleSet::query()->withCount('versions'), $filters)
            ->orderBy('country_code')->orderBy('type')->orderBy('code')->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.payroll.statutory.index', [
            'records' => $records,
            'filters' => $filters,
            'country' => strtoupper((string) setting('payroll.country_code')),
            'today' => now()->toDateString(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', StatutoryRuleSet::class);

        return view('admin.payroll.statutory.form', [
            'record' => new StatutoryRuleSet(['country_code' => setting('payroll.country_code') ?: null, 'type' => StatutoryType::IncomeTax, 'is_active' => true]),
            'types' => StatutoryType::cases(),
        ]);
    }

    public function store(StatutoryRuleSetRequest $request): RedirectResponse
    {
        $set = StatutoryRuleSet::create($request->recordData());

        return redirect()->route('admin.statutory-rules.show', $set)->with('status', __('payroll.statutory.saved', ['name' => $set->name]));
    }

    public function show(StatutoryRuleSet $statutoryRule): View
    {
        Gate::authorize('view', $statutoryRule);

        return view('admin.payroll.statutory.show', [
            'record' => $statutoryRule,
            'versions' => $statutoryRule->versions()->with(['brackets', 'creator'])->get(),
            'current' => $statutoryRule->versionOn(now()),
        ]);
    }

    public function edit(StatutoryRuleSet $statutoryRule): View
    {
        Gate::authorize('update', $statutoryRule);

        return view('admin.payroll.statutory.form', ['record' => $statutoryRule, 'types' => StatutoryType::cases()]);
    }

    public function update(StatutoryRuleSetRequest $request, StatutoryRuleSet $statutoryRule): RedirectResponse
    {
        $statutoryRule->update($request->recordData());

        return redirect()->route('admin.statutory-rules.show', $statutoryRule)->with('status', __('payroll.statutory.saved', ['name' => $statutoryRule->name]));
    }

    public function destroy(StatutoryRuleSet $statutoryRule, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($statutoryRule, $delete, 'admin.statutory-rules.index');
    }

    public function status(Request $request, StatutoryRuleSet $statutoryRule, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $statutoryRule, $changeStatus);
    }

    public function createVersion(StatutoryRuleSet $statutoryRule): View
    {
        Gate::authorize('update', $statutoryRule);

        $latest = $statutoryRule->versions()->with('brackets')->first();
        $brackets = old('brackets') ?? [
            ...($latest?->brackets->map(fn (StatutoryRuleBracket $row): array => $row->only(['lower_bound', 'upper_bound', 'rate']))->all() ?? []),
            ...array_fill(0, self::EXTRA_BRACKETS, ['lower_bound' => null, 'upper_bound' => null, 'rate' => null]),
        ];

        return view('admin.payroll.statutory.version', [
            'record' => $statutoryRule,
            'latest' => $latest,
            'brackets' => array_values($brackets),
            'effectiveFrom' => ($latest?->effective_from->greaterThanOrEqualTo(now()->startOfYear()) ? $latest->effective_from->addYear()->startOfYear() : now()->startOfYear()->addYear())->toDateString(),
        ]);
    }

    public function storeVersion(StatutoryVersionRequest $request, StatutoryRuleSet $statutoryRule, SaveStatutoryRuleVersion $save): RedirectResponse
    {
        $version = $save->handle($statutoryRule, $request->validated(), $request->user());

        return redirect()->route('admin.statutory-rules.show', $statutoryRule)
            ->with('status', __('payroll.statutory.version_saved', ['version' => $version->version, 'date' => $version->effective_from->toDateString()]));
    }
}
