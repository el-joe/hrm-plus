<?php

namespace App\Http\Controllers\Admin\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Recruitment\StageRequest;
use App\Models\RecruitmentStage;
use App\Models\Vacancy;
use App\Services\Recruitment\Pipeline;
use App\Services\Recruitment\RecruitmentOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Pipeline stages (recruitment.manage): the company default pipeline, or
 * with ?vacancy= the own pipeline of a vacancy that was customised.
 * A stage that holds applications cannot be deleted (deactivate it instead).
 */
class RecruitmentStageController extends Controller
{
    public function index(Request $request, Pipeline $pipeline): View
    {
        $vacancy = $this->vacancy($request);

        return view('admin.recruitment.stages.index', [
            'vacancy' => $vacancy,
            'stages' => ($vacancy ? $pipeline->stagesFor($vacancy, activeOnly: false) : $pipeline->defaultStages())->loadCount('applications'),
        ]);
    }

    public function create(Request $request, RecruitmentOptions $options): View
    {
        $vacancy = $this->vacancy($request);

        return view('admin.recruitment.stages.form', [
            'stage' => new RecruitmentStage(['vacancy_id' => $vacancy?->id, 'is_active' => true]),
            'vacancy' => $vacancy,
            'categories' => $options->stageCategories(),
        ]);
    }

    public function store(StageRequest $request): RedirectResponse
    {
        $vacancyId = $request->vacancyId();
        $max = RecruitmentStage::query()->where(fn ($query) => $vacancyId === null ? $query->whereNull('vacancy_id') : $query->where('vacancy_id', $vacancyId))->max('sort_order');

        RecruitmentStage::create([
            ...$request->safe()->except('vacancy_id'),
            'vacancy_id' => $vacancyId,
            'notify_candidate' => $request->boolean('notify_candidate'),
            'is_active' => $request->boolean('is_active'),
            'sort_order' => (int) $max + 10,
        ]);

        return redirect()->route('admin.recruitment-stages.index', array_filter(['vacancy' => $vacancyId]))->with('status', __('recruitment.messages.stage_saved'));
    }

    public function edit(RecruitmentStage $stage, RecruitmentOptions $options): View
    {
        $this->authorizeStage($stage);

        return view('admin.recruitment.stages.form', [
            'stage' => $stage,
            'vacancy' => $stage->vacancy,
            'categories' => $options->stageCategories(),
        ]);
    }

    public function update(StageRequest $request, RecruitmentStage $stage): RedirectResponse
    {
        $stage->update([
            ...$request->safe()->except('vacancy_id'),
            'notify_candidate' => $request->boolean('notify_candidate'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.recruitment-stages.index', array_filter(['vacancy' => $stage->vacancy_id]))->with('status', __('recruitment.messages.stage_saved'));
    }

    /**
     * Swap the stage with its neighbour (direction up / down).
     */
    public function move(Request $request, RecruitmentStage $stage): RedirectResponse
    {
        $this->authorizeStage($stage);
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        DB::transaction(function () use ($stage, $direction): void {
            $siblings = RecruitmentStage::query()
                ->where(fn ($query) => $stage->vacancy_id === null ? $query->whereNull('vacancy_id') : $query->where('vacancy_id', $stage->vacancy_id))
                ->ordered()->lockForUpdate()->get()->values();

            $index = $siblings->search(fn (RecruitmentStage $item) => $item->is($stage));
            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || ! $siblings->has($target)) {
                return;
            }

            $order = $siblings->pluck('id')->all();
            [$order[$index], $order[$target]] = [$order[$target], $order[$index]];

            foreach ($order as $position => $id) {
                RecruitmentStage::query()->whereKey($id)->update(['sort_order' => ($position + 1) * 10]);
            }
        });

        return back()->with('status', __('recruitment.messages.stage_moved'));
    }

    public function destroy(RecruitmentStage $stage): RedirectResponse
    {
        $this->authorizeStage($stage);

        if ($stage->applications()->withoutGlobalScopes()->exists()) {
            return back()->with('error', __('recruitment.messages.stage_in_use'));
        }

        $stage->delete();

        return redirect()->route('admin.recruitment-stages.index', array_filter(['vacancy' => $stage->vacancy_id]))->with('status', __('recruitment.messages.stage_deleted'));
    }

    private function vacancy(Request $request): ?Vacancy
    {
        abort_unless($request->user()->can('recruitment.manage'), 403);

        if (! $request->filled('vacancy')) {
            return null;
        }

        $vacancy = Vacancy::query()->findOrFail($request->integer('vacancy'));
        abort_unless($vacancy->uses_custom_pipeline, 404);

        return $vacancy;
    }

    private function authorizeStage(RecruitmentStage $stage): void
    {
        abort_unless(request()->user()->can('recruitment.manage'), 403);

        if ($stage->vacancy_id !== null) {
            abort_unless(Vacancy::query()->whereKey($stage->vacancy_id)->exists(), 404);
        }
    }
}
