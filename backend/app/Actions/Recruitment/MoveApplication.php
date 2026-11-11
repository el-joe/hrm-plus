<?php

namespace App\Actions\Recruitment;

use App\Enums\StageCategory;
use App\Models\JobApplication;
use App\Models\RecruitmentStage;
use App\Models\User;
use App\Notifications\Recruitment\ApplicationStageChanged;
use App\Services\Audit\AuditLogger;
use App\Services\Recruitment\CandidateNotifier;
use App\Services\Recruitment\Pipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only way an application changes stage (board drag and drop, the
 * keyboard / list "move to" control, automatic moves). Records the history
 * row, derives the application status from the stage category and e-mails
 * the candidate when the stage asks for it.
 *
 * A "hired" stage is reached only through ConvertCandidateToEmployee, and a
 * hired application never moves again.
 */
class MoveApplication
{
    public function __construct(
        private readonly Pipeline $pipeline,
        private readonly CandidateNotifier $notifier,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(
        JobApplication $application,
        RecruitmentStage $stage,
        ?User $actor = null,
        ?string $note = null,
        ?string $rejectionReason = null,
        bool $allowHired = false,
        bool $notifyCandidate = true,
    ): JobApplication {
        $application->loadMissing(['vacancy', 'stage', 'candidate']);

        if (! $this->pipeline->belongsToPipeline($application->vacancy, $stage) || ! $stage->is_active) {
            throw ValidationException::withMessages(['stage_id' => __('recruitment.validation.stage_not_in_pipeline')]);
        }

        if ($application->employee_id !== null) {
            throw ValidationException::withMessages(['stage_id' => __('recruitment.validation.already_hired')]);
        }

        if ($stage->category === StageCategory::Hired && ! $allowHired) {
            throw ValidationException::withMessages(['stage_id' => __('recruitment.validation.hire_through_conversion')]);
        }

        if ((int) $application->stage_id === (int) $stage->getKey()) {
            return $application;
        }

        $from = $application->stage;

        DB::transaction(function () use ($application, $stage, $from, $actor, $note, $rejectionReason): void {
            $application->forceFill([
                'stage_id' => $stage->getKey(),
                'status' => $stage->category->applicationStatus(),
                'stage_changed_at' => now(),
                'rejection_reason' => $stage->category === StageCategory::Rejected ? ($rejectionReason ?? $application->rejection_reason) : null,
            ])->save();

            $application->stageChanges()->create([
                'from_stage_id' => $from?->getKey(),
                'to_stage_id' => $stage->getKey(),
                'from_stage_name' => $from ? ($from->name_en ?: $from->name_ar) : null,
                'to_stage_name' => $stage->name_en ?: $stage->name_ar,
                'note' => $note,
                'changed_by' => $actor?->getKey(),
                'created_at' => now(),
            ]);

            $this->audit->log('recruitment.application_moved', $application, ['stage' => $from?->code], ['stage' => $stage->code], actor: $actor);
        });

        $application->setRelation('stage', $stage);

        if ($notifyCandidate && $stage->notify_candidate) {
            $this->notifier->send($application->candidate, new ApplicationStageChanged($application, $stage));
        }

        return $application;
    }
}
