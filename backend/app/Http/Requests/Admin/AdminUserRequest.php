<?php

namespace App\Http\Requests\Admin;

use App\Enums\DataScopeType;
use App\Enums\UserStatus;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use App\Rules\ExistsInScope;
use App\Services\Authorization\DataScopeManager;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Shared rules for creating and updating an admin account.
 *
 * Besides field validation it blocks privilege escalation: nobody changes
 * their own roles or data scopes, a role can only be handed out by someone
 * who holds all of its permissions, and a data-restricted admin can only give
 * restrictions inside their own (never an unrestricted account).
 */
abstract class AdminUserRequest extends FormRequest
{
    /**
     * The account being edited, or null when creating.
     */
    abstract protected function target(): ?User;

    /**
     * Data scopes may arrive as arrays of ids or as comma-separated text
     * ("3, 7"); normalise both to arrays of id strings.
     */
    protected function prepareForValidation(): void
    {
        $scopes = [];

        foreach ((array) $this->input('scopes', []) as $type => $ids) {
            $ids = is_array($ids) ? $ids : preg_split('/[\s,]+/', (string) $ids, -1, PREG_SPLIT_NO_EMPTY);
            $scopes[$type] = array_values(array_filter($ids, fn ($id): bool => $id !== null && $id !== ''));
        }

        $this->merge(['scopes' => $scopes]);
    }

    /**
     * @return array<string, list<ValidationRule|Closure|Password|string>>
     */
    public function rules(): array
    {
        $target = $this->target();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target)],
            'password' => [$target ? 'nullable' : 'required', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::enum(UserStatus::class), $this->cannotDeactivateSelf(...)],
            'locale' => ['nullable', 'string', Rule::in(array_keys(config('app.supported_locales')))],
            // An admin may be linked to the employee profile of the person
            // (spec section 2): it must exist, be inside the actor's data scope
            // and not be linked to another account already.
            'employee_id' => ['nullable', 'integer', 'min:1', Rule::exists('employees', 'id'), new ExistsInScope(Employee::class, keep: $target?->employee_id, activeOnly: false), Rule::unique('users', 'employee_id')->ignore($target)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [
                'integer',
                Rule::exists('roles', 'id')->where('guard_name', config('permissions.guard')),
            ],
            'scopes' => ['array:'.implode(',', array_column(DataScopeType::cases(), 'value'))],
            'scopes.*' => ['array'],
            'scopes.*.*' => ['integer', 'min:1'],
            'scopes.'.DataScopeType::Branch->value.'.*' => [Rule::exists('branches', 'id')],
            'scopes.'.DataScopeType::Department->value.'.*' => [Rule::exists('departments', 'id')],
            'scopes.'.DataScopeType::EmployeeGroup->value.'.*' => [Rule::exists('employee_groups', 'id')],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['roles', 'roles.*', 'scopes', 'scopes.*', 'scopes.*.*'])) {
                    return;
                }

                $this->ensureOwnAccessIsUnchanged($validator);
                $this->ensureRolesAreGrantable($validator);
                $this->ensureScopesStayInsideActorScopes($validator);
            },
        ];
    }

    private function ensureOwnAccessIsUnchanged(Validator $validator): void
    {
        $target = $this->target();

        if ($target === null || ! $target->is($this->user())) {
            return;
        }

        if ($this->requestedRoleIds() !== $this->currentRoleIds() || $this->requestedScopes() !== $target->dataScopeIds()) {
            $validator->errors()->add('roles', __('app.admin_users.cannot_change_own_access'));
        }
    }

    /**
     * A non Super Admin may only add a role whose every permission they hold.
     * Roles the account already has may stay, so editing other fields works.
     */
    private function ensureRolesAreGrantable(Validator $validator): void
    {
        $actor = $this->user();

        if ($actor->isSuperAdmin()) {
            return;
        }

        $currentRoleIds = $this->currentRoleIds();
        $actorPermissions = $actor->getAllPermissions()->pluck('name');
        $roles = Role::query()->with('permissions')->whereKey($this->requestedRoleIds())->get()->keyBy('id');

        foreach ((array) $this->input('roles', []) as $index => $roleId) {
            $role = $roles->get((int) $roleId);

            if ($role === null || in_array($role->id, $currentRoleIds, true)) {
                continue;
            }

            if ($role->isSuperAdminRole()) {
                $validator->errors()->add("roles.{$index}", __('app.admin_users.cannot_grant_super_admin'));
            } elseif ($role->permissions->pluck('name')->diff($actorPermissions)->isNotEmpty()) {
                $validator->errors()->add("roles.{$index}", __('app.admin_users.cannot_grant_role', ['role' => $role->displayName()]));
            }
        }
    }

    /**
     * A data-restricted admin may only add scope ids they are restricted to
     * themselves, and may not leave an account unrestricted unless it already was.
     */
    private function ensureScopesStayInsideActorScopes(Validator $validator): void
    {
        $actor = $this->user();

        if (! $actor->hasDataRestrictions()) {
            return;
        }

        $requested = $this->requestedScopes();
        $current = $this->target()?->dataScopeIds() ?? [];
        $actorScopes = $actor->dataScopeIds();
        // A department restriction covers its sub-departments, so those may be handed out too.
        $allowedScopes = app(DataScopeManager::class)->effectiveScopeIds($actor);

        if ($requested === [] && ($this->target() === null || $current !== [])) {
            $validator->errors()->add('scopes', __('app.admin_users.scopes_outside_own', $this->describeScopes($actorScopes)));

            return;
        }

        foreach ($requested as $type => $ids) {
            $added = array_diff($ids, $current[$type] ?? []);

            if (array_diff($added, $allowedScopes[$type] ?? []) !== []) {
                $validator->errors()->add('scopes', __('app.admin_users.scopes_outside_own', $this->describeScopes($actorScopes)));

                return;
            }
        }
    }

    /**
     * @param  array<string, list<int>>  $scopes
     * @return array{type: string, ids: string}
     */
    private function describeScopes(array $scopes): array
    {
        $type = (string) array_key_first($scopes);

        return ['type' => DataScopeType::from($type)->label(), 'ids' => implode(', ', $scopes[$type])];
    }

    /**
     * @return list<int>
     */
    private function requestedRoleIds(): array
    {
        return collect((array) $this->input('roles', []))->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
    }

    /**
     * @return list<int>
     */
    private function currentRoleIds(): array
    {
        return $this->target()?->roles()->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all() ?? [];
    }

    /**
     * The requested scopes in the shape of User::dataScopeIds(): type => ascending unique ids, empty types dropped.
     *
     * @return array<string, list<int>>
     */
    private function requestedScopes(): array
    {
        return collect((array) $this->input('scopes', []))
            ->map(fn (array $ids): array => collect($ids)->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all())
            ->filter()
            ->sortKeys()
            ->all();
    }

    private function cannotDeactivateSelf(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->target()?->is($this->user()) && $value !== UserStatus::Active->value) {
            $fail(__('app.admin_users.cannot_deactivate_self'));
        }
    }
}
