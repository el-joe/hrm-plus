<?php

namespace App\Policies;

use App\Models\DisciplinaryCase;
use App\Models\User;

/**
 * Disciplinary case files are restricted:
 *
 * - view (the full file: description, notes, evidence, actions, response): disciplinary.view
 *   inside the data scope. Every view is logged by the controller.
 * - viewSummary: the reporter of the case (disciplinary.create) sees its reference, employee,
 *   violation type and status only — never the investigation, and never once the case is about them.
 * - update (investigate, notes, evidence, findings, propose, dismiss, close): disciplinary.update
 *   in scope; never on a case about yourself.
 */
class DisciplinaryCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accessDisciplinary');
    }

    public function view(User $user, DisciplinaryCase $case): bool
    {
        return $user->can('disciplinary.view') && $case->isVisibleTo($user) && ! $this->isAbout($user, $case);
    }

    public function viewSummary(User $user, DisciplinaryCase $case): bool
    {
        return $this->view($user, $case)
            || ($user->can('disciplinary.create') && $case->reported_by === $user->id && ! $this->isAbout($user, $case));
    }

    public function create(User $user): bool
    {
        return $user->can('disciplinary.create');
    }

    public function update(User $user, DisciplinaryCase $case): bool
    {
        return $user->can('disciplinary.update') && $this->view($user, $case);
    }

    public function export(User $user): bool
    {
        return $user->can('disciplinary.export') && $user->can('disciplinary.view');
    }

    private function isAbout(User $user, DisciplinaryCase $case): bool
    {
        return $user->employee_id !== null && $user->employee_id === $case->employee_id;
    }
}
