<?php

namespace App\Services\Disciplinary;

use App\Enums\DisciplinaryActionStatus;
use App\Models\DisciplinaryAction;
use App\Models\Employee;
use App\Models\User;
use App\Services\Employees\Contracts\TimelineSource;
use App\Services\Employees\SensitiveDataAccessLog;
use Illuminate\Support\Collection;

/**
 * History timeline events: the disciplinary actions issued to the employee
 * (restricted — only for disciplinary.view holders, never about yourself; logged).
 */
class DisciplinaryTimeline implements TimelineSource
{
    public function __construct(private readonly SensitiveDataAccessLog $accessLog) {}

    public function entries(User $user, Employee $employee): Collection
    {
        if (! $user->can('disciplinary.view') || $user->employee_id === $employee->id) {
            return collect();
        }

        $actions = DisciplinaryAction::query()->where('employee_id', $employee->id)
            ->where('status', DisciplinaryActionStatus::Approved)
            ->with(['disciplinaryCase.violationType'])->get();

        if ($actions->isNotEmpty()) {
            $this->accessLog->record(SensitiveDataAccessLog::DISCIPLINARY, $employee, ['issued_actions'], ['tab' => 'history', 'count' => $actions->count()], $user);
        }

        return $actions->map(fn (DisciplinaryAction $action): array => [
            'date' => $action->effective_date,
            'kind' => 'event',
            'id' => $action->id,
            'module' => 'disciplinary',
            'title' => $action->type->label(),
            'description' => $action->disciplinaryCase->reference.' · '.$action->disciplinaryCase->violationType->name,
            'url' => route('admin.disciplinary-cases.show', $action->disciplinary_case_id),
            'tone' => $action->type->tone(),
            'badge' => $action->isActiveWarning() ? __('disciplinary.warnings.active') : null,
        ])->values();
    }
}
