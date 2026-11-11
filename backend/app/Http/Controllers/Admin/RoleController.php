<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\SaveRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Role;
use App\Services\Authorization\PermissionCatalogue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly PermissionCatalogue $catalogue) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        return view('admin.roles.index', [
            'roles' => Role::query()
                ->where('guard_name', config('permissions.guard'))
                ->withCount(['permissions', 'users'])
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return view('admin.roles.create', $this->formData(new Role));
    }

    public function store(RoleRequest $request, SaveRole $saveRole): RedirectResponse
    {
        $role = $saveRole->handle($request->validated());

        return redirect()->route('admin.roles.edit', $role)->with('status', __('app.roles.created'));
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);

        return view('admin.roles.edit', $this->formData($role));
    }

    public function update(RoleRequest $request, Role $role, SaveRole $saveRole): RedirectResponse
    {
        $saveRole->handle($request->validated(), $role);

        return redirect()->route('admin.roles.edit', $role)->with('status', __('app.roles.updated'));
    }

    public function destroy(Role $role, DeleteRole $deleteRole): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $deleteRole->handle($role);

        return redirect()->route('admin.roles.index')->with('status', __('app.roles.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Role $role): array
    {
        return [
            'role' => $role,
            'modules' => $this->catalogue->modules(),
            'actions' => $this->catalogue->actions(),
            'granted' => $role->exists ? $role->permissions->pluck('name')->all() : [],
        ];
    }
}
