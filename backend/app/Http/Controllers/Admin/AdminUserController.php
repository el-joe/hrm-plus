<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminUsers\CreateAdminUser;
use App\Actions\AdminUsers\UpdateAdminUser;
use App\Enums\DataScopeType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Http\Requests\Admin\UpdateAdminUserRequest;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeGroup;
use App\Models\Role;
use App\Models\User;
use App\Services\Authorization\DataScopeManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'role' => ['nullable', 'integer'],
        ]);

        $admins = User::query()
            ->admins()
            ->with('roles')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $query) => $query->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%"),
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn (Builder $query, int|string $role) => $query->whereHas(
                'roles', fn (Builder $roles) => $roles->whereKey($role),
            ))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.admin-users.index', [
            'admins' => $admins,
            'roles' => $this->roles(),
            'statuses' => UserStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.admin-users.create', $this->formData(new User));
    }

    public function store(StoreAdminUserRequest $request, CreateAdminUser $createAdminUser): RedirectResponse
    {
        $admin = $createAdminUser->handle($request->validated());

        return redirect()->route('admin.admin-users.show', $admin)->with('status', __('app.admin_users.created'));
    }

    public function show(User $adminUser, DataScopeManager $dataScopes): View
    {
        Gate::authorize('view', $adminUser);

        return view('admin.admin-users.show', [
            'admin' => $adminUser->load('roles'),
            'permissions' => $adminUser->getAllPermissions()->pluck('name')->sort()->values(),
            'scopes' => $adminUser->dataScopeIds(),
            'scopeLabels' => $dataScopes->describe($adminUser->dataScopeIds()),
            'linkedEmployee' => $adminUser->employee_id ? Employee::query()->withoutGlobalScopes()->find($adminUser->employee_id) : null,
            'scopeTypes' => DataScopeType::cases(),
            'recentLogins' => $adminUser->loginHistories()->latest('id')->limit(10)->get(),
        ]);
    }

    public function edit(User $adminUser): View
    {
        Gate::authorize('update', $adminUser);

        return view('admin.admin-users.edit', $this->formData($adminUser));
    }

    public function update(UpdateAdminUserRequest $request, User $adminUser, UpdateAdminUser $updateAdminUser): RedirectResponse
    {
        $updateAdminUser->handle($adminUser, $request->validated());

        return redirect()->route('admin.admin-users.show', $adminUser)->with('status', __('app.admin_users.updated'));
    }

    public function destroy(User $adminUser): RedirectResponse
    {
        Gate::authorize('delete', $adminUser);

        $adminUser->delete();

        return redirect()->route('admin.admin-users.index')->with('status', __('app.admin_users.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $admin): array
    {
        return [
            'admin' => $admin,
            'roles' => $this->roles(),
            'assignedRoleIds' => $admin->exists ? $admin->roles->pluck('id')->all() : [],
            'statuses' => UserStatus::cases(),
            'scopeTypes' => DataScopeType::cases(),
            'scopes' => $admin->exists ? $admin->dataScopeIds() : [],
            // Only records the signed-in admin can see (data scopes apply), so a
            // restricted admin can only hand out restrictions inside their own.
            'scopeOptions' => [
                DataScopeType::Branch->value => Branch::query()->orderBy('code')->get(),
                DataScopeType::Department->value => Department::query()->with('branch')->orderBy('code')->get(),
                DataScopeType::EmployeeGroup->value => EmployeeGroup::query()->orderBy('code')->get(),
            ],
            // Employee profiles the account may be linked to: in the actor's
            // scope and not linked to another account yet.
            'employees' => Employee::query()
                ->where(fn (Builder $query) => $query->whereDoesntHave('user')
                    ->when($admin->employee_id, fn (Builder $query, int $id) => $query->orWhere('employees.id', $id)))
                ->orderBy('employee_number')
                ->get(['id', 'employee_number', 'name_ar', 'name_en']),
            'locales' => array_keys(config('app.supported_locales')),
        ];
    }

    /**
     * @return Collection<int, Role>
     */
    private function roles()
    {
        return Role::query()->where('guard_name', config('permissions.guard'))->orderBy('name')->get();
    }
}
