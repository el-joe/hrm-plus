<?php

namespace App\Actions\AdminUsers;

use App\Models\User;
use App\Services\Authorization\AdminAccessManager;
use Illuminate\Support\Facades\DB;

class UpdateAdminUser
{
    public function __construct(private readonly AdminAccessManager $access) {}

    /**
     * @param  array{name: string, email: string, password?: string|null, status: string, locale?: string|null, employee_id?: int|null, roles: list<int>, scopes?: array<string, list<int>>}  $data
     */
    public function handle(User $admin, array $data): User
    {
        return DB::transaction(function () use ($admin, $data): User {
            $admin->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => $data['status'],
                'locale' => $data['locale'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
            ]);

            if (filled($data['password'] ?? null)) {
                $admin->password = $data['password'];
                $admin->password_changed_at = now();
            }

            $admin->save();

            $this->access->syncRoles($admin, $data['roles']);
            $this->access->syncDataScopes($admin, $data['scopes'] ?? []);

            return $admin;
        });
    }
}
