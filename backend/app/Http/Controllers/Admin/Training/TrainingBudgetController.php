<?php

namespace App\Http\Controllers\Admin\Training;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Training\TrainingBudgetRequest;
use App\Models\Department;
use App\Models\TrainingBudget;
use App\Services\Training\TrainingBudgets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Training budgets of a year (company-wide and per department) with spent, pending
 * (requests awaiting approval), remaining and % used — all from
 * App\Services\Training\TrainingBudgets (bcmath).
 */
class TrainingBudgetController extends Controller
{
    public function index(Request $request, TrainingBudgets $budgets): View
    {
        Gate::authorize('viewAny', TrainingBudget::class);

        $year = (int) ($request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']])['year'] ?? now()->year);
        $rows = $budgets->forYear($year);
        $years = TrainingBudget::query()->distinct()->orderByDesc('year')->pluck('year')->push(now()->year)->push(now()->year + 1)->unique()->sortDesc()->values();

        return view('admin.training.budgets.index', [
            'year' => $year,
            'years' => $years,
            'rows' => $rows,
            'totalSpent' => $budgets->totalSpent($year),
            'departments' => Department::query()->withoutGlobalScopes()->orderByName()->get(),
            'edit' => $request->integer('edit') ? TrainingBudget::query()->find($request->integer('edit')) : null,
            'currency' => (string) setting('localisation.currency_code'),
        ]);
    }

    public function store(TrainingBudgetRequest $request): RedirectResponse
    {
        $budget = TrainingBudget::create([...$request->validated(), 'created_by' => $request->user()->id]);

        return redirect()->route('admin.training-budgets.index', ['year' => $budget->year])->with('status', __('training.budgets.saved'));
    }

    public function update(TrainingBudgetRequest $request, TrainingBudget $trainingBudget): RedirectResponse
    {
        $trainingBudget->update($request->validated());

        return redirect()->route('admin.training-budgets.index', ['year' => $trainingBudget->year])->with('status', __('training.budgets.saved'));
    }

    public function destroy(TrainingBudget $trainingBudget): RedirectResponse
    {
        Gate::authorize('delete', $trainingBudget);
        $trainingBudget->delete();

        return redirect()->route('admin.training-budgets.index', ['year' => $trainingBudget->year])->with('status', __('training.budgets.deleted'));
    }
}
