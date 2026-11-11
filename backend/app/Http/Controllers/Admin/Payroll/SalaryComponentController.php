<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Actions\Payroll\SaveSalaryComponent;
use App\Actions\Structure\ChangeStructureRecordStatus;
use App\Actions\Structure\DeleteStructureRecord;
use App\Enums\PayCalculationType;
use App\Enums\PayComponentCategory;
use App\Enums\PayComponentKind;
use App\Enums\PayRounding;
use App\Enums\StatutoryType;
use App\Http\Controllers\Admin\Structure\StructureController;
use App\Http\Requests\Admin\Payroll\SalaryComponentRequest;
use App\Models\SalaryComponent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The salary component catalogue: earnings, deductions and employer
 * contributions with their rule type and payroll treatment. Components in
 * use are deactivated instead of deleted.
 */
class SalaryComponentController extends StructureController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', SalaryComponent::class);

        $filters = $this->filters($request);
        $filters += $request->validate(['kind' => ['nullable', 'in:'.implode(',', array_column(PayComponentKind::cases(), 'value'))]]);

        $records = $this->applyFilters(SalaryComponent::query()->with('baseComponent'), $filters)
            ->when($filters['kind'] ?? null, fn ($query, string $kind) => $query->where('kind', $kind))
            ->ordered()->paginate(self::PER_PAGE)->withQueryString();

        return view('admin.payroll.components.index', ['records' => $records, 'filters' => $filters, 'kinds' => PayComponentKind::cases()]);
    }

    public function create(): View
    {
        Gate::authorize('create', SalaryComponent::class);

        return $this->form(new SalaryComponent([
            'kind' => PayComponentKind::Earning, 'category' => PayComponentCategory::Allowance, 'calculation_type' => PayCalculationType::Fixed,
            'is_taxable' => true, 'is_insurable' => false, 'prorate_partial_period' => true, 'show_on_payslip' => true,
            'rounding' => PayRounding::HalfUp, 'display_order' => (int) SalaryComponent::query()->max('display_order') + 10, 'is_active' => true,
        ]));
    }

    public function store(SalaryComponentRequest $request, SaveSalaryComponent $save): RedirectResponse
    {
        $component = $save->handle(null, $request->recordData());

        return redirect()->route('admin.salary-components.index')->with('status', __('payroll.components.saved', ['name' => $component->name]));
    }

    public function edit(SalaryComponent $salaryComponent): View
    {
        Gate::authorize('update', $salaryComponent);

        return $this->form($salaryComponent);
    }

    public function update(SalaryComponentRequest $request, SalaryComponent $salaryComponent, SaveSalaryComponent $save): RedirectResponse
    {
        $save->handle($salaryComponent, $request->recordData());

        return redirect()->route('admin.salary-components.index')->with('status', __('payroll.components.saved', ['name' => $salaryComponent->name]));
    }

    public function destroy(SalaryComponent $salaryComponent, DeleteStructureRecord $delete): RedirectResponse
    {
        return $this->deleteRecord($salaryComponent, $delete, 'admin.salary-components.index');
    }

    public function status(Request $request, SalaryComponent $salaryComponent, ChangeStructureRecordStatus $changeStatus): RedirectResponse
    {
        return $this->updateStatus($request, $salaryComponent, $changeStatus);
    }

    private function form(SalaryComponent $component): View
    {
        return view('admin.payroll.components.form', [
            'record' => $component,
            'kinds' => PayComponentKind::cases(),
            'categories' => PayComponentCategory::cases(),
            'types' => PayCalculationType::cases(),
            'statutoryTypes' => StatutoryType::cases(),
            'roundings' => PayRounding::cases(),
            'bases' => SalaryComponent::query()->activeOrKeep($component->base_component_id)
                ->when($component->exists, fn ($query) => $query->whereKeyNot($component->id))
                ->where('calculation_type', '!=', PayCalculationType::Statutory)->ordered()->get(),
        ]);
    }
}
