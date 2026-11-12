<?php

namespace App\Actions\Performance;

use App\Enums\GoalStatus;
use App\Enums\PerformanceCycleStatus;
use App\Enums\PerformanceCycleType;
use App\Enums\PerformanceReviewStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Notifications\Performance\PerformanceReviewNotification;
use App\Services\Audit\AuditLogger;
use App\Support\ReferenceNumbers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Life of a review cycle:
 *
 * save():   create / edit. A draft can change everything; an active cycle only its
 *           names, description and due dates; a closed one nothing.
 * launch(): draft → active. Creates one review per participant (active employees of
 *           the branch / department filter — sub-departments included; probation
 *           cycles: employees whose probation ends inside the period), reviewer = the
 *           employee's manager, competencies copied with their weights, status
 *           self_assessment (or manager_assessment when self-assessment is off).
 * close():  active → closed. Goals of the cycle still active are marked completed (the
 *           period is over; their last progress is kept). Open reviews stay as they are
 *           (still visible) but the cycle takes no new goals.
 * delete(): drafts only.
 */
class ManagePerformanceCycle
{
    public function __construct(private readonly AuditLogger $audit, private readonly ReferenceNumbers $references) {}

    /**
     * @param  array<string, mixed>  $data  cycle columns + competencies: list<array{competency_id: int, weight: string}>
     *
     * @throws ValidationException
     */
    public function save(?PerformanceCycle $cycle, array $data, User $user): PerformanceCycle
    {
        if ($cycle?->isClosed()) {
            throw ValidationException::withMessages(['name_ar' => __('performance.validation.cycle_closed')]);
        }

        if ($cycle !== null && ! $cycle->isDraft()) {
            $cycle->update(array_intersect_key($data, array_flip(['name_ar', 'name_en', 'description', 'self_assessment_due', 'manager_assessment_due'])));

            return $cycle;
        }

        $competencies = collect($data['competencies'] ?? [])
            ->filter(fn (array $row): bool => filled($row['competency_id'] ?? null) && filled($row['weight'] ?? null))
            ->unique('competency_id')->values();
        unset($data['competencies']);

        $this->checkWeights($data, $competencies);

        return DB::transaction(function () use ($cycle, $data, $competencies, $user): PerformanceCycle {
            if ($cycle === null) {
                $cycle = PerformanceCycle::create([...$data, 'created_by' => $user->id]);
            } else {
                $cycle->update($data);
                $cycle->competencies()->delete();
            }

            foreach ($competencies as $index => $row) {
                $cycle->competencies()->create(['competency_id' => (int) $row['competency_id'], 'weight' => bcadd((string) $row['weight'], '0', 2), 'sort_order' => ($index + 1) * 10]);
            }

            return $cycle->refresh();
        });
    }

    /**
     * Employees the cycle would review if launched now.
     *
     * @return Collection<int, Employee>
     */
    public function participants(PerformanceCycle $cycle): Collection
    {
        $departmentIds = $cycle->department_id ? [$cycle->department_id, ...Department::query()->withoutGlobalScopes()->findOrFail($cycle->department_id)->descendantIds()] : null;

        return Employee::query()->withoutGlobalScopes()->active()
            ->when($cycle->branch_id, fn ($query, int $branch) => $query->where('branch_id', $branch))
            ->when($departmentIds, fn ($query, array $ids) => $query->whereIn('department_id', $ids))
            ->when($cycle->type === PerformanceCycleType::Probation, fn ($query) => $query
                ->whereBetween('probation_end_date', [$cycle->period_start->toDateString(), $cycle->period_end->toDateString()]))
            ->where('hire_date', '<=', $cycle->period_end->toDateString())
            ->orderBy('employee_number')
            ->get();
    }

    /**
     * @throws ValidationException
     */
    public function launch(PerformanceCycle $cycle, User $user): int
    {
        if (! $cycle->isDraft()) {
            throw ValidationException::withMessages(['cycle' => __('performance.validation.cycle_not_draft')]);
        }

        $cycle->loadMissing(['competencies', 'ratingScale.levels']);

        if ($cycle->ratingScale->levels->count() < 2) {
            throw ValidationException::withMessages(['cycle' => __('performance.validation.scale_levels')]);
        }

        $participants = $this->participants($cycle);

        if ($participants->isEmpty()) {
            throw ValidationException::withMessages(['cycle' => __('performance.validation.no_participants')]);
        }

        $status = $cycle->self_assessment_enabled ? PerformanceReviewStatus::SelfAssessment : PerformanceReviewStatus::ManagerAssessment;

        $reviews = DB::transaction(function () use ($cycle, $participants, $status, $user): Collection {
            $reviews = collect();

            foreach ($participants as $employee) {
                $review = PerformanceReview::create([
                    'reference' => $this->references->next('PRV'),
                    'performance_cycle_id' => $cycle->id,
                    'employee_id' => $employee->id,
                    'reviewer_id' => $employee->manager_id,
                    'branch_id' => $employee->branch_id,
                    'department_id' => $employee->department_id,
                    'status' => $status,
                ]);

                foreach ($cycle->competencies as $row) {
                    $review->competencies()->create(['competency_id' => $row->competency_id, 'weight' => $row->weight, 'sort_order' => $row->sort_order]);
                }

                $reviews->push($review);
            }

            $cycle->forceFill(['status' => PerformanceCycleStatus::Active, 'launched_at' => now(), 'launched_by' => $user->id])->save();
            $this->audit->log('performance.cycle_launched', $cycle, meta: ['reviews' => $reviews->count()], actor: $user);

            return $reviews;
        });

        $this->notifyLaunch($reviews, $status);

        return $reviews->count();
    }

    /**
     * @throws ValidationException
     */
    public function close(PerformanceCycle $cycle, User $user): void
    {
        if (! $cycle->isActive()) {
            throw ValidationException::withMessages(['cycle' => __('performance.validation.cycle_not_active')]);
        }

        DB::transaction(function () use ($cycle, $user): void {
            $completed = PerformanceGoal::query()->withoutGlobalScopes()->where('performance_cycle_id', $cycle->id)->where('status', GoalStatus::Active->value)->update(['status' => GoalStatus::Completed->value, 'updated_at' => now()]);
            $cycle->forceFill(['status' => PerformanceCycleStatus::Closed, 'closed_at' => now(), 'closed_by' => $user->id])->save();
            $this->audit->log('performance.cycle_closed', $cycle, meta: ['goals_completed' => $completed], actor: $user);
        });
    }

    /**
     * @throws ValidationException
     */
    public function delete(PerformanceCycle $cycle): void
    {
        if (! $cycle->isDraft() || $cycle->reviews()->withoutGlobalScopes()->exists() || $cycle->goals()->withoutGlobalScopes()->exists()) {
            throw ValidationException::withMessages(['cycle' => __('performance.validation.cycle_not_deletable')]);
        }

        DB::transaction(function () use ($cycle): void {
            $cycle->competencies()->delete();
            $cycle->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, array<string, mixed>>  $competencies
     *
     * @throws ValidationException
     */
    private function checkWeights(array $data, Collection $competencies): void
    {
        if (bccomp(bcadd((string) $data['goals_weight'], (string) $data['competencies_weight'], 2), '100', 2) !== 0) {
            throw ValidationException::withMessages(['goals_weight' => __('performance.validation.split_total')]);
        }

        if (bccomp((string) $data['competencies_weight'], '0', 2) > 0 && $competencies->isEmpty()) {
            throw ValidationException::withMessages(['competencies' => __('performance.validation.competencies_required')]);
        }

        if ($competencies->isNotEmpty()) {
            $total = $competencies->reduce(fn (string $carry, array $row): string => bcadd($carry, (string) $row['weight'], 2), '0');

            if (bccomp($total, '100', 2) !== 0) {
                throw ValidationException::withMessages(['competencies' => __('performance.validation.competency_weights', ['total' => $total])]);
            }
        }
    }

    /**
     * @param  Collection<int, PerformanceReview>  $reviews
     */
    private function notifyLaunch(Collection $reviews, PerformanceReviewStatus $status): void
    {
        foreach ($reviews as $review) {
            if ($status === PerformanceReviewStatus::SelfAssessment) {
                foreach (User::query()->where('employee_id', $review->employee_id)->get() as $user) {
                    $user->notify(new PerformanceReviewNotification($review, 'launched'));
                }

                continue;
            }

            if ($review->reviewer_id !== null) {
                foreach (User::query()->admins()->where('employee_id', $review->reviewer_id)->get() as $user) {
                    $user->notify(new PerformanceReviewNotification($review, 'launched_reviewer'));
                }
            }
        }
    }
}
