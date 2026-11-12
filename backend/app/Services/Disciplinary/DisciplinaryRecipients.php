<?php

namespace App\Services\Disciplinary;

use App\Models\DisciplinaryCase;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Support\Collection;

/**
 * Active admin accounts holding disciplinary.view whose data scope covers the
 * case (the people told about a new incident or an employee's response).
 * The employee concerned is never a recipient.
 */
class DisciplinaryRecipients
{
    public function __construct(private readonly DataScopeManager $scopes) {}

    /**
     * @return Collection<int, User>
     */
    public function viewersOf(DisciplinaryCase $case, ?User $except = null): Collection
    {
        $permission = 'disciplinary.view';

        $candidates = User::query()->admins()->where(fn ($query) => $query
            ->whereHas('roles.permissions', fn ($row) => $row->where('name', $permission))
            ->orWhereHas('permissions', fn ($row) => $row->where('name', $permission)))->get();

        return $this->scopes->withoutScoping(fn (): Collection => $candidates
            ->filter(fn (User $user): bool => $user->isActive()
                && $user->id !== $except?->id
                && $user->employee_id !== $case->employee_id
                && $case->isVisibleTo($user))
            ->values());
    }
}
