<?php

namespace App\Http\Requests\Admin;

use App\Models\Role;
use App\Services\Authorization\PermissionCatalogue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or update a role and its permission matrix.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->role();

        return $role ? $this->user()->can('update', $role) : $this->user()->can('create', Role::class);
    }

    /**
     * @return array<string, list<ValidationRule|Closure|string>>
     */
    public function rules(): array
    {
        $role = $this->role();

        return [
            'name' => [
                $role?->is_system ? 'sometimes' : 'required', 'string', 'max:100',
                Rule::unique('roles', 'name')->where('guard_name', config('permissions.guard'))->ignore($role),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(app(PermissionCatalogue::class)->all()), $this->actorHoldsPermission(...)],
        ];
    }

    private function role(): ?Role
    {
        return $this->route('role');
    }

    /**
     * An admin can only add permissions they hold themselves, so editing
     * roles can never be used to escalate privileges. Permissions the role
     * already has may stay, so the rest of the role can still be edited.
     */
    private function actorHoldsPermission(string $attribute, mixed $value, Closure $fail): void
    {
        $alreadyGranted = $this->role()?->permissions->contains('name', $value) ?? false;

        if (is_string($value) && ! $alreadyGranted && ! $this->user()->can($value)) {
            $fail(__('app.roles.cannot_grant_permission', ['permission' => $value]));
        }
    }
}
