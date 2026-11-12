<?php

namespace App\Console\Commands;

use App\Actions\AdminUsers\CreateAdminUser;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\PermissionSynchroniser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * First Super Admin of a fresh installation (production seeds no accounts). Interactive:
 * the password is asked without echo and never accepted on the command line, so it does
 * not end up in shell history. Non-interactive installs pass it in HRM_ADMIN_PASSWORD.
 */
#[Signature('hrm:create-super-admin {--name= : Display name} {--email= : Sign-in e-mail address}')]
#[Description('Create a Super Admin account (first sign-in of a fresh installation)')]
class CreateSuperAdmin extends Command
{
    public function handle(CreateAdminUser $create, PermissionSynchroniser $permissions, AuditLogger $audit): int
    {
        $roleName = (string) config('permissions.super_admin_role');

        if (Role::query()->where('name', $roleName)->doesntExist()) {
            $permissions->sync();
        }

        $name = (string) ($this->option('name') ?: $this->ask('Name', 'Super Admin'));
        $email = (string) ($this->option('email') ?: $this->ask('E-mail'));
        $password = (string) (getenv('HRM_ADMIN_PASSWORD') ?: $this->secret('Password (not shown)'));
        $confirmation = getenv('HRM_ADMIN_PASSWORD') ?: (string) $this->secret('Repeat the password');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:'.User::class.',email'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $admin = $create->handle([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'status' => UserStatus::Active->value,
            'roles' => [Role::query()->where('name', $roleName)->value('id')],
        ]);
        $audit->log('admin_user.created_from_console', $admin, meta: ['role' => $roleName]);

        $this->components->info("Super Admin {$email} created. Sign in at ".route('admin.login').'.');

        return self::SUCCESS;
    }
}
