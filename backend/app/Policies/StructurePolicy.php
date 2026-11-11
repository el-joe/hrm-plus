<?php

namespace App\Policies;

use App\Models\Concerns\HasDataScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared rules of the organisation-structure records. Each subclass names the
 * permission module ("branches", "departments", ...). Records that use
 * HasDataScope must also be inside the admin's data scope.
 */
abstract class StructurePolicy
{
    /**
     * Permission module of config/permissions.php.
     */
    abstract protected function module(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->module().'.view');
    }

    public function view(User $user, Model $record): bool
    {
        return $user->can($this->module().'.view') && $this->inScope($user, $record);
    }

    public function create(User $user): bool
    {
        return $user->can($this->module().'.create');
    }

    /**
     * Also covers activating / deactivating.
     */
    public function update(User $user, Model $record): bool
    {
        return $user->can($this->module().'.update') && $this->inScope($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->can($this->module().'.delete') && $this->inScope($user, $record);
    }

    protected function inScope(User $user, Model $record): bool
    {
        return ! in_array(HasDataScope::class, class_uses_recursive($record), true) || $record->isVisibleTo($user);
    }
}
