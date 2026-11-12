<?php

namespace App\Models;

use App\Enums\OnboardingStatus;
use App\Enums\TaskStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasDataScope;
use App\Models\Scopes\DataScope;
use Database\Factories\OnboardingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A running onboarding of an employee: tasks copied from a template.
 * Scoped through the employee.
 */
#[Fillable(['employee_id', 'onboarding_template_id', 'job_application_id', 'start_date', 'status', 'created_by'])]
class Onboarding extends Model
{
    /** @use HasFactory<OnboardingFactory> */
    use Auditable, HasDataScope, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OnboardingStatus::class,
            'start_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function dataScopeMap(): array
    {
        return [
            'branch' => 'employee.branch_id',
            'department' => 'employee.department_id',
            'employee_group' => 'employee.groups.id',
        ];
    }

    /**
     * Finished share of the tasks, 0–100 (done and skipped both count).
     */
    public function progress(): int
    {
        $tasks = $this->relationLoaded('tasks') ? $this->tasks : $this->tasks()->get();

        if ($tasks->isEmpty()) {
            return $this->status === OnboardingStatus::Completed ? 100 : 0;
        }

        return intdiv($tasks->filter(fn (OnboardingTask $task): bool => $task->status->isFinished())->count() * 100, $tasks->count());
    }

    public function pendingCount(): int
    {
        return $this->tasks->where('status', TaskStatus::Pending)->count();
    }

    public function isInProgress(): bool
    {
        return $this->status === OnboardingStatus::InProgress;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(DataScope::class);
    }

    /**
     * @return BelongsTo<OnboardingTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OnboardingTemplate::class, 'onboarding_template_id');
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /**
     * @return HasMany<OnboardingTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class)->orderBy('sort_order')->orderBy('id');
    }
}
