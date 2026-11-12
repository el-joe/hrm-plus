<?php

namespace App\Services\Reports\Definitions\Recruitment;

use App\Enums\StageCategory;

/**
 * SQL fragments shared by the conversion reports: whether an application ever reached a stage
 * category (its current stage, or any stage it was moved to — application_stage_changes).
 */
trait ConversionQueries
{
    /**
     * @param  list<StageCategory>  $categories
     */
    protected function reached(array $categories): string
    {
        $list = "'".implode("','", array_map(fn (StageCategory $category): string => $category->value, $categories))."'";

        return "(exists (select 1 from recruitment_stages current_stage where current_stage.id = job_applications.stage_id and current_stage.category in ({$list}))"
            .' or exists (select 1 from application_stage_changes changes join recruitment_stages moved on moved.id = changes.to_stage_id'
            ." where changes.job_application_id = job_applications.id and moved.category in ({$list})))";
    }

    protected function reachedInterview(): string
    {
        return $this->reached([StageCategory::Interview, StageCategory::Offer, StageCategory::Hired]);
    }

    protected function reachedOffer(): string
    {
        return $this->reached([StageCategory::Offer, StageCategory::Hired]);
    }
}
