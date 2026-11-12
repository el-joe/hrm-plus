<?php

namespace App\Actions\Performance;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\PerformanceReviewStatus;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Performance\PerformanceScoring;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Goals and KPIs: set by HR / the manager (admin) or by the employee in the portal
 * (setting performance.employees_can_add_goals). Goals of a cycle are locked once the
 * employee's review of that cycle has left the assessment steps, and a closed cycle
 * takes no new goals. Progress updates are history rows; a KPI's progress follows its
 * actual value against the target.
 */
class ManagePerformanceGoal
{
    public function __construct(private readonly AuditLogger $audit, private readonly PerformanceScoring $scoring) {}

    /**
     * @param  array<string, mixed>  $data  performance_cycle_id, type, title, description, unit, target_value, current_value, weight, start_date, due_date
     *
     * @throws ValidationException
     */
    public function create(Employee $employee, array $data, User $user): PerformanceGoal
    {
        $cycle = $this->cycle($data['performance_cycle_id'] ?? null);
        $this->ensureEditable($employee->id, $cycle);

        return DB::transaction(function () use ($employee, $data, $cycle, $user): PerformanceGoal {
            $goal = PerformanceGoal::create([
                ...$this->attributes($data),
                'employee_id' => $employee->id,
                'performance_cycle_id' => $cycle?->id,
                'status' => GoalStatus::Active,
                'created_by' => $user->id,
            ]);

            if ($goal->isKpi() && $goal->current_value !== null) {
                $goal->forceFill(['progress' => $this->scoring->kpiProgress((string) $goal->current_value, (string) $goal->target_value) ?? '0.00'])->save();
            }

            return $goal;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function update(PerformanceGoal $goal, array $data, User $user): PerformanceGoal
    {
        $this->ensureEditable($goal->employee_id, $goal->cycle);

        $goal->fill($this->attributes($data));

        if ($goal->isKpi()) {
            $goal->progress = $this->scoring->kpiProgress($goal->current_value === null ? null : (string) $goal->current_value, $goal->target_value === null ? null : (string) $goal->target_value) ?? $goal->progress;
        }

        $goal->save();

        return $goal;
    }

    /**
     * Record progress: a percentage, or for a KPI the actual value (progress derived).
     *
     * @throws ValidationException
     */
    public function recordProgress(PerformanceGoal $goal, ?string $progress, ?string $currentValue, ?string $note, User $user): PerformanceGoal
    {
        if ($goal->status === GoalStatus::Cancelled) {
            throw ValidationException::withMessages(['progress' => __('performance.validation.goal_cancelled')]);
        }

        if ($goal->isKpi() && filled($currentValue)) {
            $progress = $this->scoring->kpiProgress(bcadd($currentValue, '0', 2), $goal->target_value === null ? null : (string) $goal->target_value) ?? $progress;
        }

        if (! filled($progress)) {
            throw ValidationException::withMessages(['progress' => __('performance.validation.progress_required')]);
        }

        $progress = bcadd((string) $progress, '0', 2);

        if (bccomp($progress, '0', 2) < 0 || bccomp($progress, '100', 2) > 0) {
            throw ValidationException::withMessages(['progress' => __('performance.validation.progress_range')]);
        }

        return DB::transaction(function () use ($goal, $progress, $currentValue, $note, $user): PerformanceGoal {
            $goal->updates()->create([
                'progress' => $progress,
                'current_value' => filled($currentValue) ? bcadd($currentValue, '0', 2) : null,
                'note' => $note,
                'created_by' => $user->id,
            ]);

            $goal->forceFill([
                'progress' => $progress,
                'current_value' => filled($currentValue) ? bcadd($currentValue, '0', 2) : $goal->current_value,
                'status' => bccomp($progress, '100', 2) === 0 && $goal->status === GoalStatus::Active ? GoalStatus::Completed : $goal->status,
            ])->save();

            return $goal;
        });
    }

    /**
     * Active, completed or cancelled (cancelled goals leave weights and scores).
     *
     * @throws ValidationException
     */
    public function changeStatus(PerformanceGoal $goal, GoalStatus $status, User $user): PerformanceGoal
    {
        $this->ensureEditable($goal->employee_id, $goal->cycle);
        $goal->forceFill(['status' => $status])->save();
        $this->audit->log('performance.goal_status', $goal, meta: ['status' => $status->value], actor: $user);

        return $goal;
    }

    /**
     * @throws ValidationException
     */
    private function cycle(mixed $id): ?PerformanceCycle
    {
        if (! filled($id)) {
            return null;
        }

        $cycle = PerformanceCycle::query()->find($id);

        if ($cycle === null || $cycle->isClosed()) {
            throw ValidationException::withMessages(['performance_cycle_id' => __('performance.validation.cycle_closed')]);
        }

        return $cycle;
    }

    /**
     * Goals of a cycle can change only while the employee's review is still being assessed.
     *
     * @throws ValidationException
     */
    private function ensureEditable(int $employeeId, ?PerformanceCycle $cycle): void
    {
        if ($cycle === null) {
            return;
        }

        if ($cycle->isClosed()) {
            throw ValidationException::withMessages(['performance_cycle_id' => __('performance.validation.cycle_closed')]);
        }

        $review = PerformanceReview::query()->withoutGlobalScopes()->where('performance_cycle_id', $cycle->id)->where('employee_id', $employeeId)->first();

        if ($review !== null && ! $review->hasStatus(PerformanceReviewStatus::SelfAssessment, PerformanceReviewStatus::ManagerAssessment)) {
            throw ValidationException::withMessages(['title' => __('performance.validation.goals_locked')]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $type = GoalType::from($data['type'] ?? GoalType::Goal->value);
        $kpi = $type === GoalType::Kpi;

        return [
            'type' => $type,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'unit' => $kpi ? ($data['unit'] ?? null) : null,
            'target_value' => $kpi && filled($data['target_value'] ?? null) ? bcadd((string) $data['target_value'], '0', 2) : null,
            'current_value' => $kpi && filled($data['current_value'] ?? null) ? bcadd((string) $data['current_value'], '0', 2) : null,
            'weight' => bcadd((string) ($data['weight'] ?? '0'), '0', 2),
            'start_date' => $data['start_date'] ?? null,
            'due_date' => $data['due_date'] ?? null,
        ];
    }
}
