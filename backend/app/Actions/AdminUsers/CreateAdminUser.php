<?php

namespace App\Actions\AdminUsers;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Authorization\AdminAccessManager;
use Illuminate\Support\Facades\DB;

class CreateAdminUser
{
    public function __construct(private readonly AdminAccessManager $access) {}

    /**
     * @param  array{name: string, email: string, password: string, status: string, locale?: string|null, employee_id?: int|null, roles: list<int>, scopes?: array<string, list<int>>}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $admin = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => $data['status'],
                'locale' => $data['locale'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
            ]);
            $admin->account_type = AccountType::Admin;
            $admin->password_changed_at = now();
            $admin->save();

            $this->access->syncRoles($admin, $data['roles']);
            $this->access->syncDataScopes($admin, $data['scopes'] ?? []);

            return $admin;
        });
    }
}
