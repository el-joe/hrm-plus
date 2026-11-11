<?php

namespace App\Services\Recruitment;

use App\Enums\StageCategory;
use App\Models\RecruitmentStage;
use App\Models\Vacancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pipeline stages of a vacancy: its own stages when it uses a custom
 * pipeline, otherwise the company default (recruitment_stages.vacancy_id NULL).
 */
class Pipeline
{
    /**
     * Active stages in board order.
     *
     * @return Collection<int, RecruitmentStage>
     */
    public function stagesFor(Vacancy $vacancy, bool $activeOnly = true): Collection
    {
        return RecruitmentStage::query()
            ->when($vacancy->uses_custom_pipeline, fn ($query) => $query->where('vacancy_id', $vacancy->getKey()), fn ($query) => $query->defaultPipeline())
            ->when($activeOnly, fn ($query) => $query->active())
            ->ordered()
            ->get();
    }

    /**
     * @return Collection<int, RecruitmentStage>
     */
    public function defaultStages(bool $activeOnly = false): Collection
    {
        return RecruitmentStage::query()->defaultPipeline()->when($activeOnly, fn ($query) => $query->active())->ordered()->get();
    }

    /**
     * Where new applications start: the first active "open" stage.
     */
    public function entryStage(Vacancy $vacancy): ?RecruitmentStage
    {
        $stages = $this->stagesFor($vacancy);

        return $stages->firstWhere('category', StageCategory::Open) ?? $stages->first(fn (RecruitmentStage $stage) => ! $stage->category->isTerminal());
    }

    public function firstOfCategory(Vacancy $vacancy, StageCategory $category): ?RecruitmentStage
    {
        return $this->stagesFor($vacancy)->firstWhere('category', $category);
    }

    public function belongsToPipeline(Vacancy $vacancy, RecruitmentStage $stage): bool
    {
        return $vacancy->uses_custom_pipeline
            ? (int) $stage->vacancy_id === (int) $vacancy->getKey()
            : $stage->vacancy_id === null;
    }

    /**
     * Give the vacancy its own copy of the default pipeline. Applications
     * keep their place: they are moved to the copy of their current stage.
     */
    public function customise(Vacancy $vacancy): void
    {
        if ($vacancy->uses_custom_pipeline) {
            return;
        }

        DB::transaction(function () use ($vacancy): void {
            $map = [];

            foreach ($this->defaultStages() as $stage) {
                $copy = $vacancy->stages()->create($stage->only(['code', 'name_ar', 'name_en', 'category', 'sort_order', 'notify_candidate', 'is_active']));
                $map[$stage->getKey()] = $copy->getKey();
            }

            foreach ($map as $from => $to) {
                $vacancy->applications()->where('stage_id', $from)->update(['stage_id' => $to]);
            }

            $vacancy->forceFill(['uses_custom_pipeline' => true])->save();
        });
    }

    /**
     * Go back to the company default; applications move to the default stage
     * with the same code, or the default stage of the same category.
     */
    public function useDefault(Vacancy $vacancy): void
    {
        if (! $vacancy->uses_custom_pipeline) {
            return;
        }

        DB::transaction(function () use ($vacancy): void {
            $defaults = $this->defaultStages();

            foreach ($vacancy->stages()->get() as $stage) {
                $target = $defaults->firstWhere('code', $stage->code) ?? $defaults->firstWhere('category', $stage->category) ?? $defaults->first();
                $vacancy->applications()->where('stage_id', $stage->getKey())->update(['stage_id' => $target->getKey()]);
            }

            $vacancy->forceFill(['uses_custom_pipeline' => false])->save();
            $vacancy->stages()->get()->each->delete();
        });
    }

    /**
     * Seed the company default pipeline from config('recruitment.default_stages') when it is empty.
     */
    public function seedDefaults(): void
    {
        if (RecruitmentStage::query()->defaultPipeline()->exists()) {
            return;
        }

        foreach (config('recruitment.default_stages') as $index => [$code, $nameAr, $nameEn, $category, $notify]) {
            RecruitmentStage::create([
                'vacancy_id' => null, 'code' => $code, 'name_ar' => $nameAr, 'name_en' => $nameEn,
                'category' => $category, 'sort_order' => ($index + 1) * 10, 'notify_candidate' => $notify, 'is_active' => true,
            ]);
        }
    }
}
