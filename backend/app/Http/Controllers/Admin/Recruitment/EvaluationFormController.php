<?php

namespace App\Http\Controllers\Admin\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Recruitment\EvaluationFormRequest;
use App\Models\EvaluationForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Interview evaluation forms and their weighted criteria (recruitment.manage).
 * A criterion already scored on a scorecard is kept (only renamed / reweighted);
 * a form in use is deactivated instead of deleted.
 */
class EvaluationFormController extends Controller
{
    public function index(): View
    {
        return view('admin.recruitment.evaluation-forms.index', [
            'forms' => EvaluationForm::query()->withCount(['criteria', 'interviews'])->orderBy(EvaluationForm::localizedColumn())->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.recruitment.evaluation-forms.form', ['form' => new EvaluationForm(['is_active' => true])]);
    }

    public function store(EvaluationFormRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $form = EvaluationForm::create([...$request->safe()->except('criteria'), 'is_active' => $request->boolean('is_active')]);
            $this->syncCriteria($form, $request->validated('criteria'));
        });

        return redirect()->route('admin.evaluation-forms.index')->with('status', __('recruitment.messages.form_saved'));
    }

    public function edit(EvaluationForm $evaluationForm): View
    {
        return view('admin.recruitment.evaluation-forms.form', ['form' => $evaluationForm->load('criteria')]);
    }

    public function update(EvaluationFormRequest $request, EvaluationForm $evaluationForm): RedirectResponse
    {
        DB::transaction(function () use ($request, $evaluationForm): void {
            $evaluationForm->update([...$request->safe()->except('criteria'), 'is_active' => $request->boolean('is_active')]);
            $this->syncCriteria($evaluationForm, $request->validated('criteria'));
        });

        return redirect()->route('admin.evaluation-forms.index')->with('status', __('recruitment.messages.form_saved'));
    }

    public function destroy(EvaluationForm $evaluationForm): RedirectResponse
    {
        if ($evaluationForm->interviews()->withoutGlobalScopes()->exists()) {
            $evaluationForm->update(['is_active' => false]);

            return back()->with('status', __('recruitment.messages.form_deactivated'));
        }

        $evaluationForm->delete();

        return redirect()->route('admin.evaluation-forms.index')->with('status', __('recruitment.messages.form_deleted'));
    }

    /**
     * @param  list<array{id?: int|null, name_ar: string, name_en: string, description?: string|null, weight: int}>  $rows
     */
    private function syncCriteria(EvaluationForm $form, array $rows): void
    {
        $kept = [];

        foreach (array_values($rows) as $index => $row) {
            $attributes = [
                'name_ar' => $row['name_ar'], 'name_en' => $row['name_en'], 'description' => $row['description'] ?? null,
                'weight' => (int) $row['weight'], 'sort_order' => ($index + 1) * 10,
            ];
            $criterion = filled($row['id'] ?? null) ? $form->criteria()->whereKey($row['id'])->first() : null;
            $criterion ? $criterion->update($attributes) : $criterion = $form->criteria()->create($attributes);
            $kept[] = $criterion->getKey();
        }

        $form->criteria()->whereNotIn('id', $kept)
            ->whereNotExists(fn ($query) => $query->from('interview_scores')->whereColumn('interview_scores.evaluation_criterion_id', 'evaluation_criteria.id'))
            ->delete();
    }
}
